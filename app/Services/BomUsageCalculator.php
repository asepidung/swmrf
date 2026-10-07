<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Mengusulkan pemakaian bahan penolong dari BOM (`.agents/hpp.md` §3,
 * issue #344) berdasarkan hasil sebuah proses (`boning_items` atau
 * `repack_results` -- keduanya berbentuk sama: satu baris = satu BOX
 * berbarcode, `qty_pcs` isinya).
 *
 * BOM di sini hanya MENGUSULKAN, tidak pernah menulis apa pun sendiri --
 * potongan stok tetap keputusan manusia yang menekan tombol simpan
 * (cerita Owner 7 September 2026: menghitung plastik satu per satu di
 * lapangan tidak mungkin, satu dus bisa 5.000 lembar).
 */
class BomUsageCalculator
{
    /**
     * @param  Collection<int, object{product_id:int, qty_pcs:int|null}>  $items
     * @return array{
     *     usage: array<int, float>,
     *     products: array<int, array{product_id: int, product_name: string, box: int, pcs: int}>,
     *     skipped: array<int, array{product_id: int, product_name: string, material_id: int, material_name: string}>,
     *     without_bom: array<int, array{product_id: int, product_name: string}>,
     * }
     */
    public static function calculate(Collection $items): array
    {
        $usage = [];
        $products = [];
        $skipped = [];
        $withoutBom = [];

        foreach ($items->groupBy('product_id') as $productId => $rows) {
            $product = Product::find($productId);

            if (! $product) {
                continue;
            }

            $box = $rows->count();
            $pcs = (int) $rows->sum('qty_pcs');

            $products[] = [
                'product_id' => (int) $productId,
                'product_name' => $product->name,
                'box' => $box,
                'pcs' => $pcs,
            ];

            $bomRows = $product->billOfMaterials()->with('material')->get();

            if ($bomRows->isEmpty()) {
                $withoutBom[] = ['product_id' => (int) $productId, 'product_name' => $product->name];

                continue;
            }

            foreach ($bomRows as $bom) {
                if ($bom->jumlahnyaTidakTetap()) {
                    $skipped[] = [
                        'product_id' => (int) $productId,
                        'product_name' => $product->name,
                        'material_id' => $bom->material_id,
                        'material_name' => $bom->material?->name ?? '-',
                    ];

                    continue;
                }

                $dasar = $bom->basis === 'piece' ? $pcs : $box;
                $qty = $bom->quantity * $dasar;

                $usage[$bom->material_id] = ($usage[$bom->material_id] ?? 0) + $qty;
            }
        }

        return [
            'usage' => $usage,
            'products' => $products,
            'skipped' => $skipped,
            'without_bom' => $withoutBom,
        ];
    }
}
