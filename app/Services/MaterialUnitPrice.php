<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Facades\DB;

/**
 * Harga sebuah material per SATUAN PAKAI (mis. per pcs), untuk menilai bahan
 * terbuang (issue #509, keputusan Owner 7 Oktober 2026).
 *
 * Material dibeli per satuan beli (1 Box plastik @ Rp 1.000.000, isi 1.000
 * pcs); BOM dan bahan terbuang dihitung per satuan pakai. Karena itu:
 *
 *     harga per satuan pakai = harga beli per satuan beli / content_per_unit
 *
 * Harga beli per satuan beli diambil, berurutan:
 *   1. rata-rata TERTIMBANG (qty x harga) dari item Goods Receipt Material
 *      yang sah -- baris dan dokumen GR-nya belum dihapus;
 *   2. bila belum pernah ada GR, harga PO material TERAKHIR;
 *   3. bila tidak ada harga sama sekali: null. Pemanggil mencatatnya dengan
 *      nilai 0 dan menandainya "belum ada harga" -- tidak diblokir.
 *
 * Stok, PO, dan GR tidak berubah; ini hanya membaca.
 */
class MaterialUnitPrice
{
    /**
     * @return float|null  null = tidak ada harga sama sekali
     */
    public static function perUsageUnit(Material $material): ?float
    {
        $purchasePrice = self::perPurchaseUnit($material->id);

        if ($purchasePrice === null) {
            return null;
        }

        return $purchasePrice / max(1, (int) $material->content_per_unit);
    }

    public static function perPurchaseUnit(int $materialId): ?float
    {
        $weighted = DB::table('goods_receipt_material_items as i')
            ->join('goods_receipt_materials as g', 'g.id', '=', 'i.goods_receipt_material_id')
            ->where('i.material_id', $materialId)
            ->whereNull('i.deleted_at')
            ->whereNull('g.deleted_at')
            ->where('i.qty_received', '>', 0)
            ->whereNotNull('i.price')
            ->selectRaw('SUM(i.qty_received * i.price) as total, SUM(i.qty_received) as qty')
            ->first();

        if ($weighted && (float) $weighted->qty > 0) {
            return (float) $weighted->total / (float) $weighted->qty;
        }

        $lastPo = DB::table('purchase_material_items as i')
            ->join('purchase_materials as p', 'p.id', '=', 'i.purchase_material_id')
            ->where('i.material_id', $materialId)
            ->whereNull('p.deleted_at')
            ->whereNotNull('i.price')
            ->orderByDesc('p.po_date')
            ->orderByDesc('i.id')
            ->value('i.price');

        return $lastPo !== null ? (float) $lastPo : null;
    }
}
