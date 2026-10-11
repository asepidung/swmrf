<?php

namespace App\Support;

use App\Models\BarcodeCost;
use App\Models\BoningItem;
use App\Models\Costing;
use App\Models\Repack;
use App\Models\RepackMaterial;
use App\Services\RepackValuator;
use Illuminate\Support\Facades\DB;

/**
 * Satu rumah untuk nilai per barcode (HPP tahap 2, issue #522).
 *
 * Modul lain membaca nilai lewat `for()` / `valueOf()` dan menulisnya lewat
 * metode di sini -- tidak ada yang menyentuh `barcode_costs` langsung.
 *
 * NULL berarti BELUM bernilai dan tidak pernah dianggap nol: `valueOf()`
 * mengembalikan null, bukan 0, supaya laporan bisa memisahkan "belum bernilai"
 * dari "bernilai nol".
 *
 * Penulisan idempoten: menulis nilai yang sama dua kali tidak mengubah apa pun
 * (dan tidak menambah baris activity log).
 */
class BarcodeCosts
{
    /** Batas kedalaman rambatan (relabel berantai); mencegah putaran tak berujung. */
    public const MAX_DEPTH = 10;

    /** Harga pokok per kg sebuah barcode; null = belum bernilai atau tidak dikenal. */
    public static function for(?string $barcode): ?float
    {
        if (blank($barcode)) {
            return null;
        }

        $cost = BarcodeCost::where('barcode', $barcode)->value('cost_per_kg');

        return $cost === null ? null : (float) $cost;
    }

    /** Nilai rupiah $kg kilogram barcode itu; null = belum bernilai. */
    public static function valueOf(?string $barcode, float $kg): ?float
    {
        $cost = self::for($barcode);

        return $cost === null ? null : round($kg * $cost, 2);
    }

    /**
     * Pastikan barcode punya baris (belum bernilai bila baru). Tidak menyentuh
     * baris yang sudah ada -- nilainya tetap.
     */
    public static function ensure(string $barcode, ?int $productId, string $sourceType, ?int $sourceId = null, ?string $sourceBarcode = null): BarcodeCost
    {
        return BarcodeCost::firstOrCreate(
            ['barcode' => $barcode],
            ['product_id' => $productId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'source_barcode' => $sourceBarcode],
        );
    }

    /**
     * Isi atau timpa nilai sebuah barcode. Mengembalikan true bila nilainya
     * benar-benar berubah. Rambatan ke turunan dipanggil terpisah
     * (`propagateFrom`).
     */
    public static function set(string $barcode, ?float $costPerKg, string $sourceType, ?int $sourceId = null, ?string $note = null, ?int $productId = null): bool
    {
        $costPerKg = $costPerKg === null ? null : round($costPerKg, 2);

        $row = BarcodeCost::firstOrNew(['barcode' => $barcode]);
        $row->product_id ??= $productId;

        $unchanged = $row->exists
            && $row->cost_per_kg === $costPerKg
            && $row->source_type === $sourceType
            && $row->source_id === $sourceId;

        if ($unchanged) {
            return false;
        }

        $row->fill([
            'cost_per_kg' => $costPerKg,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'valued_at' => $costPerKg === null ? null : now(),
            'note' => $note,
        ])->save();

        return true;
    }

    /**
     * Costing dikunci (atau dikunci ulang): semua barcode boning lot itu
     * bernilai `hpp_per_kg` produknya -- MUNDUR, termasuk barcode yang sudah
     * terjual atau jadi bahan repack (keputusan Owner 1 dan 2). Nilai lama
     * ditimpa, lalu dirambatkan ke turunannya. Satu transaksi, idempoten.
     *
     * Barcode yang produknya tidak ada di costing ini dibiarkan (hanya dipastikan
     * barisnya ada).
     *
     * @return int jumlah barcode yang nilainya berubah (termasuk turunannya)
     */
    public static function valueBoning(Costing $costing): int
    {
        return DB::transaction(function () use ($costing): int {
            $hpp = $costing->items()->pluck('hpp_per_kg', 'product_id')->map(fn ($v) => (float) $v);
            $changed = 0;
            $valued = [];

            // Tahap 1: nilai SEMUA barcode lot ini dulu. Tahap 2: baru rambatkan --
            // repack yang memakai banyak barcode lot ini cukup dihitung sekali per
            // barcode, dan tidak sempat `pending` di tengah jalan.
            BoningItem::where('boning_id', $costing->boning_id)
                ->whereNotNull('barcode')
                ->get(['id', 'barcode', 'product_id'])
                ->each(function (BoningItem $item) use ($costing, $hpp, &$changed, &$valued): void {
                    if (! $hpp->has($item->product_id)) {
                        self::ensure($item->barcode, $item->product_id, BarcodeCost::SOURCE_BONING);

                        return;
                    }

                    if (self::set($item->barcode, $hpp[$item->product_id], BarcodeCost::SOURCE_BONING, $costing->id, $costing->costing_number, $item->product_id)) {
                        $changed++;
                    }

                    $valued[] = $item->barcode;
                });

            foreach ($valued as $barcode) {
                $changed += self::propagateFrom($barcode);
            }

            return $changed;
        });
    }

    /**
     * Barcode lahir dari relabel: salin nilai barcode asal, dan ingat asalnya
     * untuk rambatan berikutnya.
     */
    public static function relabel(string $oldBarcode, string $newBarcode, ?int $productId): BarcodeCost
    {
        $cost = self::for($oldBarcode);

        return BarcodeCost::updateOrCreate(
            ['barcode' => $newBarcode],
            [
                'product_id' => $productId,
                'cost_per_kg' => $cost,
                'source_type' => BarcodeCost::SOURCE_RELABEL,
                'source_barcode' => $oldBarcode,
                'valued_at' => $cost === null ? null : now(),
            ],
        );
    }

    /**
     * Rambatkan nilai $barcode ke barcode yang lahir darinya lewat relabel
     * (berantai, dibatasi `MAX_DEPTH`).
     *
     * @param  array<int, string>  $visited
     * @return int jumlah turunan yang nilainya berubah
     */
    public static function propagateFrom(string $barcode, int $depth = 0, array $visited = []): int
    {
        if ($depth >= self::MAX_DEPTH || in_array($barcode, $visited, true)) {
            return 0;
        }

        $visited[] = $barcode;
        $cost = self::for($barcode);
        $changed = 0;

        $children = BarcodeCost::where('source_barcode', $barcode)
            ->where('source_type', BarcodeCost::SOURCE_RELABEL)
            ->get();

        foreach ($children as $child) {
            if ($child->cost_per_kg !== $cost) {
                $child->fill(['cost_per_kg' => $cost, 'valued_at' => $cost === null ? null : now()])->save();
                $changed++;
            }

            $changed += self::propagateFrom($child->barcode, $depth + 1, $visited);
        }

        // Repack TERKUNCI yang memakai barcode ini sebagai bahan dihitung ulang
        // (harga acuannya sudah terkunci; hanya biaya bahannya yang berubah), dan
        // hasilnya merambat lagi ke repack berikutnya.
        $repackIds = RepackMaterial::where('barcode', $barcode)->pluck('repack_id')->unique();

        foreach (Repack::whereIn('id', $repackIds)->where('kunci', true)->get() as $repack) {
            $changed += RepackValuator::recalculate($repack, $depth, $visited);
        }

        return $changed;
    }

    /**
     * GR Beef: nilai = harga beli per kg di PO, dibekukan saat barang diterima
     * (keputusan Owner 3). Harga tidak ada (<= 0) -> belum bernilai.
     */
    public static function fromGoodsReceipt(string $barcode, ?int $productId, ?float $pricePerKg, ?int $itemId = null): void
    {
        $price = $pricePerKg !== null && $pricePerKg > 0 ? $pricePerKg : null;

        self::set($barcode, $price, BarcodeCost::SOURCE_GOODS_RECEIPT, $itemId, null, $productId);
    }

    /**
     * Barcode kembali ke stok lewat retur jual atau temuan opname (keputusan
     * Owner 3). Barcode yang SUDAH punya baris kembali dengan barisnya sendiri
     * -- nilainya tidak berubah. Barcode baru (timbang manual, atau tanpa
     * barcode yang jelas) = rata-rata tertimbang harga pokok produk itu di
     * stok saat ini; tidak ada stok bernilai -> belum bernilai (NULL).
     */
    public static function fromAverage(string $barcode, ?int $productId, string $sourceType, ?int $sourceId = null): BarcodeCost
    {
        $existing = BarcodeCost::where('barcode', $barcode)->first();

        if ($existing) {
            return $existing;
        }

        $average = $productId ? self::averageInStock($productId) : null;

        return BarcodeCost::create([
            'barcode' => $barcode,
            'product_id' => $productId,
            'cost_per_kg' => $average === null ? null : round($average, 2),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'valued_at' => $average === null ? null : now(),
            'note' => $average === null ? 'no valued stock to average' : 'average cost of product in stock',
        ]);
    }

    /**
     * Rata-rata tertimbang (kg) `cost_per_kg` produk itu pada stok yang ada
     * (beef_stocks IN_STOCK) dan sudah bernilai; null bila tidak ada.
     */
    public static function averageInStock(int $productId): ?float
    {
        $row = DB::table('beef_stocks as s')
            ->join('barcode_costs as c', 'c.barcode', '=', 's.barcode')
            ->where('s.product_id', $productId)
            ->where('s.status', 'IN_STOCK')
            ->where('s.weight', '>', 0)
            ->whereNotNull('c.cost_per_kg')
            ->selectRaw('SUM(s.weight * c.cost_per_kg) as total, SUM(s.weight) as kg')
            ->first();

        return $row && (float) $row->kg > 0 ? (float) $row->total / (float) $row->kg : null;
    }

    /**
     * `hpp_per_kg` produk itu pada costing TERKUNCI yang terakhir; null bila
     * produk belum pernah di-costing. Dasar nilai stok awal (keputusan Owner,
     * 11 Oktober 2026).
     */
    public static function lastCostingHpp(int $productId): ?float
    {
        $hpp = DB::table('costing_items as i')
            ->join('costings as c', 'c.id', '=', 'i.costing_id')
            ->where('i.product_id', $productId)
            ->where('c.status', Costing::STATUS_LOCKED)
            ->whereNull('c.deleted_at')
            ->orderByDesc('c.locked_at')
            ->orderByDesc('c.id')
            ->value('i.hpp_per_kg');

        return $hpp === null ? null : (float) $hpp;
    }

    /**
     * Stok awal saat go-live: setiap barcode yang ada di stok tetapi belum punya
     * baris (stok sebelum buku ini ada) dinilai `lastCostingHpp()` produknya;
     * produk yang belum pernah di-costing -> NULL, diisi manual accounting
     * lewat layar Nilai Barcode. Barcode yang sudah punya baris tidak disentuh.
     *
     * @return array{valued: int, unvalued: int}
     */
    public static function valueOpeningStock(): array
    {
        $valued = 0;
        $unvalued = 0;
        $cache = [];

        DB::transaction(function () use (&$valued, &$unvalued, &$cache): void {
            // get(), bukan each(): baris baru mengubah hasil whereNull di tengah jalan.
            DB::table('beef_stocks as s')
                ->leftJoin('barcode_costs as c', 'c.barcode', '=', 's.barcode')
                ->whereNull('c.id')
                ->where('s.status', 'IN_STOCK')
                ->select('s.barcode', 's.product_id')
                ->orderBy('s.id')
                ->get()
                ->each(function ($stock) use (&$valued, &$unvalued, &$cache): void {
                    $cache[$stock->product_id] ??= $stock->product_id ? self::lastCostingHpp($stock->product_id) : null;
                    $hpp = $cache[$stock->product_id];

                    BarcodeCost::create([
                        'barcode' => $stock->barcode,
                        'product_id' => $stock->product_id,
                        'cost_per_kg' => $hpp,
                        'source_type' => BarcodeCost::SOURCE_OPENING,
                        'valued_at' => $hpp === null ? null : now(),
                        'note' => $hpp === null ? 'opening stock: product never costed' : 'opening stock: last locked costing',
                    ]);

                    $hpp === null ? $unvalued++ : $valued++;
                });
        });

        return ['valued' => $valued, 'unvalued' => $unvalued];
    }
}
