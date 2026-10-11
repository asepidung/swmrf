<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Support\BarcodeCosts;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Angka dua laporan HPP tahap 2 (issue #522): Nilai Stok dan HPP Penjualan.
 *
 * Seluruhnya MEMBACA nilai per barcode (`barcode_costs`); tidak ada angka
 * yang disalin ke dokumen. Barcode yang BELUM bernilai ditampilkan terpisah
 * (jumlah barcode dan kg-nya) dan tidak pernah dihitung nol diam-diam.
 *
 * Tidak menyentuh stok maupun dokumen.
 */
class CostReports
{
    /**
     * Nilai stok di tangan (beef_stocks IN_STOCK) per gudang dan produk.
     *
     * @return array{
     *     rows: array<int, array{warehouse: string, product: string, barcodes: int, kg: float, valued_kg: float, value: float, unvalued_barcodes: int, unvalued_kg: float}>,
     *     total: array{barcodes: int, kg: float, valued_kg: float, value: float, unvalued_barcodes: int, unvalued_kg: float},
     * }
     */
    public static function stockValue(?int $warehouseId = null, ?int $productId = null): array
    {
        $rows = DB::table('beef_stocks as s')
            ->leftJoin('barcode_costs as c', 'c.barcode', '=', 's.barcode')
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.status', 'IN_STOCK')
            ->when($warehouseId, fn ($q) => $q->where('s.warehouse_id', $warehouseId))
            ->when($productId, fn ($q) => $q->where('s.product_id', $productId))
            ->groupBy('s.warehouse_id', 's.product_id', 'w.name', 'p.name')
            ->orderBy('w.name')
            ->orderBy('p.name')
            ->selectRaw('w.name as warehouse, p.name as product, COUNT(*) as barcodes, SUM(s.weight) as kg,'
                .' SUM(CASE WHEN c.cost_per_kg IS NOT NULL THEN s.weight ELSE 0 END) as valued_kg,'
                .' SUM(CASE WHEN c.cost_per_kg IS NOT NULL THEN s.weight * c.cost_per_kg ELSE 0 END) as value,'
                .' SUM(CASE WHEN c.cost_per_kg IS NULL THEN 1 ELSE 0 END) as unvalued_barcodes,'
                .' SUM(CASE WHEN c.cost_per_kg IS NULL THEN s.weight ELSE 0 END) as unvalued_kg')
            ->get()
            ->map(fn ($r): array => [
                'warehouse' => $r->warehouse,
                'product' => $r->product,
                'barcodes' => (int) $r->barcodes,
                'kg' => round((float) $r->kg, 2),
                'valued_kg' => round((float) $r->valued_kg, 2),
                'value' => round((float) $r->value, 2),
                'unvalued_barcodes' => (int) $r->unvalued_barcodes,
                'unvalued_kg' => round((float) $r->unvalued_kg, 2),
            ])
            ->all();

        $total = ['barcodes' => 0, 'kg' => 0.0, 'valued_kg' => 0.0, 'value' => 0.0, 'unvalued_barcodes' => 0, 'unvalued_kg' => 0.0];

        foreach ($rows as $row) {
            foreach (array_keys($total) as $key) {
                $total[$key] += $row[$key];
            }
        }

        return ['rows' => $rows, 'total' => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $total)];
    }

    /**
     * HPP penjualan per periode (tanggal surat jalan, batas ikut dihitung): per
     * surat jalan dan produk, kg, penjualan (dari invoice surat jalan itu),
     * HPP (jumlah kg barcode di surat jalan x harga pokok barcode itu, dibaca
     * SAAT LAPORAN DIBUAT -- pengisian mundur langsung tercermin), dan margin.
     *
     * - `sales` null = surat jalan belum ditagih; margin tidak dihitung.
     * - Barcode yang belum bernilai dihitung terpisah (`unvalued_*`); margin baris
     *   itu juga tidak dihitung, karena HPP-nya belum lengkap.
     * - Retur belum memengaruhi laporan ini.
     *
     * @return array{
     *     from: string,
     *     until: string,
     *     rows: array<int, array<string, mixed>>,
     *     total: array<string, mixed>,
     *     by_customer: array<int, array<string, mixed>>,
     *     by_product: array<int, array<string, mixed>>,
     * }
     */
    public static function salesCost(CarbonInterface $from, CarbonInterface $until, ?int $customerId = null, ?int $productId = null): array
    {
        $orders = DeliveryOrder::query()
            ->with(['customer:id,name', 'tally.items:id,tally_id,barcode,product_id,weight', 'tally.items.product:id,name', 'receipt:id,delivery_order_id'])
            ->whereDate('delivery_date', '>=', $from->toDateString())
            ->whereDate('delivery_date', '<=', $until->toDateString())
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('delivery_date')
            ->orderBy('delivery_order_number')
            ->get();

        $invoices = Invoice::query()
            ->with('items:id,invoice_id,product_id,amount')
            ->whereIn('delivery_order_receipt_id', $orders->pluck('receipt.id')->filter()->all())
            ->get()
            ->keyBy('delivery_order_receipt_id');

        $rows = [];

        foreach ($orders as $order) {
            $invoice = $invoices->get($order->receipt?->id);

            foreach (($order->tally?->items ?? collect())->groupBy('product_id') as $pid => $items) {
                if ($productId && (int) $pid !== $productId) {
                    continue;
                }

                $kg = 0.0;
                $hpp = 0.0;
                $unvaluedBarcodes = 0;
                $unvaluedKg = 0.0;

                foreach ($items as $item) {
                    $kg += (float) $item->weight;
                    $cost = BarcodeCosts::for($item->barcode);

                    if ($cost === null) {
                        $unvaluedBarcodes++;
                        $unvaluedKg += (float) $item->weight;
                    } else {
                        $hpp += (float) $item->weight * $cost;
                    }
                }

                $sales = $invoice ? round((float) $invoice->items->where('product_id', $pid)->sum('amount'), 2) : null;
                $complete = $sales !== null && $unvaluedBarcodes === 0;

                $rows[] = [
                    'date' => $order->delivery_date ? Carbon::parse($order->delivery_date)->toDateString() : null,
                    'delivery_order' => $order->delivery_order_number,
                    'invoice' => $invoice?->invoice_number,
                    'customer_id' => $order->customer_id,
                    'customer' => $order->customer?->name ?? '-',
                    'product_id' => (int) $pid,
                    'product' => $items->first()->product?->name ?? '-',
                    'kg' => round($kg, 2),
                    'sales' => $sales,
                    'hpp' => round($hpp, 2),
                    'margin' => $complete ? round($sales - $hpp, 2) : null,
                    'unvalued_barcodes' => $unvaluedBarcodes,
                    'unvalued_kg' => round($unvaluedKg, 2),
                ];
            }
        }

        return [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
            'rows' => $rows,
            'total' => self::sum($rows),
            'by_customer' => self::group($rows, 'customer_id', 'customer'),
            'by_product' => self::group($rows, 'product_id', 'product'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function sum(array $rows): array
    {
        $sum = ['kg' => 0.0, 'sales' => 0.0, 'hpp' => 0.0, 'margin' => 0.0, 'unvalued_barcodes' => 0, 'unvalued_kg' => 0.0, 'not_invoiced_rows' => 0, 'margin_rows' => 0];

        foreach ($rows as $row) {
            $sum['kg'] += $row['kg'];
            $sum['hpp'] += $row['hpp'];
            $sum['unvalued_barcodes'] += $row['unvalued_barcodes'];
            $sum['unvalued_kg'] += $row['unvalued_kg'];

            if ($row['sales'] === null) {
                $sum['not_invoiced_rows']++;
            } else {
                $sum['sales'] += $row['sales'];
            }

            if ($row['margin'] !== null) {
                $sum['margin'] += $row['margin'];
                $sum['margin_rows']++;
            }
        }

        foreach (['kg', 'sales', 'hpp', 'margin', 'unvalued_kg'] as $key) {
            $sum[$key] = round($sum[$key], 2);
        }

        return $sum;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function group(array $rows, string $idKey, string $nameKey): array
    {
        return collect($rows)
            ->groupBy($idKey)
            ->map(fn ($group) => ['name' => $group->first()[$nameKey]] + self::sum($group->all()))
            ->sortBy('name')
            ->values()
            ->all();
    }
}
