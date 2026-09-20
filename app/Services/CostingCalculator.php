<?php

namespace App\Services;

use App\Models\Boning;
use App\Models\CarcassItem;
use App\Models\Costing;
use App\Models\CostingItem;
use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\PurchaseCattle;
use App\Models\PurchaseCattleItem;

/**
 * Mesin HPP -- metode Relative Sales Value (`.agents/hpp.md` §2, §6).
 * Murni: tidak menulis apa pun, hanya membaca dan menghitung. Dipanggil
 * saat Create dan saat "Hitung ulang" (Draft saja); yang menyimpan
 * hasilnya adalah pemanggil (`CreateCosting`/`RecalculateCosting`).
 *
 * ```
 * biaya_beli       = Σ per kelas sapi (berat terima kelas x harga/kg kelas)
 * net_i            = gross_i x (1 - trading_terms grup acuan produk i)
 * total_nilai_jual = Σ net_i x kg_i
 * k                = biaya_beli / total_nilai_jual
 * HPP_i / kg       = net_i x k
 * laba lot         = total_nilai_jual - biaya_beli - overhead_per_kg x Σ kg
 * ```
 *
 * Pembulatan: dihitung PENUH (float PHP biasa, tanpa pembulatan antara),
 * hanya dibulatkan 2 desimal pada NILAI AKHIR yang disimpan/ditampilkan
 * (`hpp.md`: "hitung penuh, tampilkan 2 desimal; uji ke dua desimal").
 * Membulatkan `k` lebih dulu SEBELUM dipakai mengalikan tiap produk akan
 * menggeser HPP setiap produk sekaligus -- k dibulatkan HANYA untuk
 * kolom `ratio_k` yang disimpan, bukan untuk hitungan `hpp_per_kg`.
 */
class CostingCalculator
{
    private function __construct(
        private readonly Boning $boning,
        private readonly float $overheadPerKg,
    ) {
    }

    public static function forBoning(Boning $boning, ?float $overheadPerKg = null): self
    {
        return new self($boning, $overheadPerKg ?? Costing::defaultOverheadPerKg());
    }

    /**
     * @return array{
     *     purchase_cost: float,
     *     cattle: array<int, array{cattle_class_id: int, head_count: int, received_weight: float, price_per_kg: float, amount: float}>,
     *     items: array<int, array{product_id: int, weight_kg: float, reference_group_id: ?int, gross_price: float, trading_terms_percent: float, net_price: float, sales_value: float, hpp_per_kg: float, flag: ?string}>,
     *     total_sales_value: float,
     *     ratio_k: float,
     *     total_kg: float,
     *     overhead_per_kg: float,
     *     profit: float,
     * }
     */
    public function calculate(): array
    {
        if (! $this->boning->kunci) {
            throw new \RuntimeException(__('This boning is not locked yet, so it cannot be costed.'));
        }

        $cattle = $this->cattleCost();
        $purchaseCostRaw = 0.0;
        foreach ($cattle as $row) {
            $purchaseCostRaw += $row['received_weight'] * $row['price_per_kg_raw'];
        }

        $items = $this->productItems();
        $totalSalesValueRaw = 0.0;
        $totalKgRaw = 0.0;
        foreach ($items as $item) {
            $totalSalesValueRaw += $item['sales_value_raw'];
            $totalKgRaw += $item['weight_kg'];
        }

        $ratioKRaw = $totalSalesValueRaw > 0 ? $purchaseCostRaw / $totalSalesValueRaw : 0.0;

        foreach ($items as &$item) {
            $item['hpp_per_kg'] = $item['flag'] === CostingItem::FLAG_NO_PRICE
                ? 0.0
                : round($item['net_price'] * $ratioKRaw, 2);
            unset($item['sales_value_raw']);
        }
        unset($item);

        foreach ($cattle as &$row) {
            unset($row['price_per_kg_raw']);
        }
        unset($row);

        $profit = round($totalSalesValueRaw - $purchaseCostRaw - ($this->overheadPerKg * $totalKgRaw), 2);

        return [
            'purchase_cost' => round($purchaseCostRaw, 2),
            'cattle' => $cattle,
            'items' => $items,
            'total_sales_value' => round($totalSalesValueRaw, 2),
            'ratio_k' => round($ratioKRaw, 6),
            'total_kg' => round($totalKgRaw, 2),
            'overhead_per_kg' => round($this->overheadPerKg, 2),
            'profit' => $profit,
        ];
    }

    /**
     * Biaya beli per kelas sapi -- TIDAK PERNAH satu harga dikali berat
     * total (`hpp.md` §6). Sapi yang dihitung adalah yang BENAR-BENAR
     * bagian dari carcass boning ini (lewat `CarcassItem`), bukan seluruh
     * isi `CattleReceiving`-nya -- satu PO/penerimaan bisa menaungi lebih
     * banyak ekor daripada yang dipotong untuk lot ini (`CPO-260106`: 50
     * ekor dipesan, 20 dipotong).
     */
    private function cattleCost(): array
    {
        $carcassIds = $this->boning->carcasses()->pluck('carcass_id');

        $carcassItems = CarcassItem::whereIn('carcass_id', $carcassIds)
            ->with('weighingItem.receivingItem.receiving.purchaseCattle.items')
            ->get();

        $byClass = [];

        foreach ($carcassItems as $carcassItem) {
            $receivingItem = $carcassItem->weighingItem?->receivingItem;

            if (! $receivingItem) {
                continue;
            }

            $classId = $receivingItem->cattle_class_id;
            $weight = (float) $receivingItem->initial_weight;

            if (! isset($byClass[$classId])) {
                $byClass[$classId] = [
                    'head_count' => 0,
                    'received_weight' => 0.0,
                    // Satu boning selalu satu lot supplier (hpp.md §6) --
                    // PO representatif kelas ini diambil dari ekor
                    // PERTAMA yang ditemui, bukan ditimbang-rata dari
                    // beberapa PO berbeda.
                    'purchase_cattle' => $receivingItem->receiving?->purchaseCattle,
                ];
            }

            $byClass[$classId]['head_count']++;
            $byClass[$classId]['received_weight'] += $weight;
        }

        $rows = [];

        foreach ($byClass as $classId => $data) {
            $price = $this->resolveCattlePrice((int) $classId, $data['purchase_cattle']);

            $rows[] = [
                'cattle_class_id' => (int) $classId,
                'head_count' => $data['head_count'],
                'received_weight' => round($data['received_weight'], 2),
                'price_per_kg' => round($price, 2),
                'price_per_kg_raw' => $price,
                'amount' => round($data['received_weight'] * $price, 2),
            ];
        }

        return $rows;
    }

    /**
     * Harga per kg untuk satu kelas sapi -- tiga lapis, PERSIS
     * `CattleWeighing::calculateAndSaveFinancialLoss()`: (1) PO lot ini
     * sendiri, (2) pembelian terakhir kelas yang sama dari supplier yang
     * sama, (3) rata-rata harga kelas di PO ini. Duplikasi sengaja --
     * disatukan lewat helper akan mengikat kedua fitur pada satu titik
     * ubah yang tidak jelas hubungannya kalau salah satu berubah nanti.
     */
    private function resolveCattlePrice(int $classId, ?PurchaseCattle $purchaseCattle): float
    {
        if (! $purchaseCattle) {
            return 0.0;
        }

        $poItems = $purchaseCattle->items->keyBy('cattle_class_id');

        if (isset($poItems[$classId])) {
            return (float) $poItems[$classId]->price;
        }

        $lastPurchaseItem = PurchaseCattleItem::where('cattle_class_id', $classId)
            ->whereHas('purchaseCattle', fn ($q) => $q->where('supplier_id', $purchaseCattle->supplier_id))
            ->latest('created_at')
            ->first();

        if ($lastPurchaseItem && $lastPurchaseItem->price > 0) {
            return (float) $lastPurchaseItem->price;
        }

        if ($poItems->count() > 0) {
            return (float) $poItems->avg('price');
        }

        return 0.0;
    }

    /**
     * Satu baris per produk yang MUNCUL di boning ini -- bukan seluruh
     * katalog produk seperti sheet legacy (`hpp.md` §1, §8).
     */
    private function productItems(): array
    {
        $weightsByProduct = $this->boning->items()
            ->selectRaw('product_id, SUM(weight) as total_weight')
            ->groupBy('product_id')
            ->get();

        $rows = [];

        foreach ($weightsByProduct as $row) {
            $product = Product::find($row->product_id);
            $weightKg = (float) $row->total_weight;

            $referenceGroupId = $product?->costing_customer_group_id;
            $flag = null;
            $termsPercent = 0.0;
            $grossPrice = null;

            if ($referenceGroupId) {
                $group = CustomerGroup::find($referenceGroupId);
                $termsPercent = (float) ($group->trading_terms_percent ?? 0);
                $grossPrice = $this->priceFor((int) $referenceGroupId, (int) $row->product_id);
            } else {
                // hpp.md §10: kosong = harga umum, keadaan paling banyak,
                // bukan pengecualian. §9: potongan HANYA berlaku untuk
                // LION/HYPERMART -- harga umum selalu 0% terlepas dari
                // apa pun yang tersimpan di grup acuannya.
                $flag = CostingItem::FLAG_NO_REFERENCE;
                $termsPercent = 0.0;

                $general = CustomerGroup::generalPriceReference();
                $grossPrice = $general ? $this->priceFor($general->id, (int) $row->product_id) : null;
            }

            if ($grossPrice === null) {
                $flag = CostingItem::FLAG_NO_PRICE;
                $grossPrice = 0.0;
                $netPrice = 0.0;
                $salesValue = 0.0;
            } else {
                // Net dibulatkan 2 desimal DI SINI, bukan ditunda --
                // net adalah nilai uang (persis "H = I - (I x 6%)" di
                // sheet legacy), bukan nilai antara. `k` yang menunggu
                // presisi penuh, bukan net-nya.
                $netPrice = round($grossPrice * (1 - $termsPercent / 100), 2);
                $salesValue = $netPrice * $weightKg;
            }

            $rows[] = [
                'product_id' => (int) $row->product_id,
                'weight_kg' => $weightKg,
                'reference_group_id' => $referenceGroupId,
                'gross_price' => round($grossPrice, 2),
                'trading_terms_percent' => $termsPercent,
                'net_price' => $netPrice,
                'sales_value' => round($salesValue, 2),
                'sales_value_raw' => $salesValue,
                'hpp_per_kg' => 0.0,
                'flag' => $flag,
            ];
        }

        return $rows;
    }

    private function priceFor(int $groupId, int $productId): ?float
    {
        $priceList = PriceList::where('customer_group_id', $groupId)->first();

        if (! $priceList) {
            return null;
        }

        $item = PriceListItem::where('price_list_id', $priceList->id)
            ->where('product_id', $productId)
            ->first();

        return $item ? (float) $item->price : null;
    }
}
