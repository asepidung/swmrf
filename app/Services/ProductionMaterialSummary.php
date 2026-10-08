<?php

namespace App\Services;

use App\Models\Boning;
use App\Models\Material;
use App\Models\Repack;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemakaian bahan sebuah Boning/Repack, siap tampil (issue #509, langkah 5):
 * kebutuhan menurut BOM, drylog, dan bahan terbuang berikut nilainya.
 *
 * Satu rumah untuk halaman dokumen, halaman View, dan cetakan, supaya ketiganya
 * tidak berbeda diam-diam.
 *
 * - Dokumen TERKUNCI: BOM dari snapshot yang dibekukan saat Lock, nilai bahan
 *   terbuang dari kolom yang disimpan saat Lock. Angkanya final.
 * - Dokumen BELUM terkunci: BOM dihitung ulang dari label terkini dan bahan
 *   terbuang belum bernilai (`amount` null). Ditandai `final = false`.
 *
 * Tidak menyentuh stok.
 */
class ProductionMaterialSummary
{
    /**
     * @param  Boning|Repack  $document
     * @return array{
     *     final: bool,
     *     bom: array<int, array{material: string, unit: string, qty: int|float}>,
     *     drylog: int|null,
     *     drylog_name: string|null,
     *     drylog_amount: float|null,
     *     wastes: array<int, array{material: string, unit: string, qty: int, reason: string, amount: float|null}>,
     *     waste_total: float|null,
     *     unpriced: int,
     * }
     */
    public static function for(Model $document): array
    {
        $final = (bool) $document->kunci;

        $usage = $final
            ? $document->bomSnapshots()->pluck('qty', 'material_id')->all()
            : BomUsageCalculator::calculate($document->bomLabels())['usage'];

        $materials = Material::with('unit')
            ->whereIn('id', array_keys($usage))
            ->get()
            ->keyBy('id');

        $bom = [];
        foreach ($usage as $materialId => $qty) {
            $material = $materials->get($materialId);
            $bom[] = [
                'material' => $material?->name ?? '-',
                // BOM dihitung per satuan PAKAI (pcs), bukan satuan beli material.
                'unit' => 'pcs',
                'qty' => $qty,
            ];
        }
        usort($bom, fn (array $a, array $b): int => strcmp($a['material'], $b['material']));

        $wastes = [];
        $total = 0.0;
        $unpriced = 0;

        // Dokumen yang dikunci SEBELUM nilai per baris mulai disimpan (langkah 5a)
        // belum punya `amount` di barisnya. Cadangannya: baris Financial Loss
        // yang ditulis saat Lock, dipasangkan lewat catatannya ("bahan: alasan")
        // -- persis yang ditulis `writeMaterialWasteLosses()`.
        $legacyLosses = $final
            ? $document->materialWasteLosses()->get()->groupBy('note')
            : collect();

        foreach ($document->materialWastes()->with('material.unit')->orderBy('id')->get() as $waste) {
            $amount = null;

            if ($final) {
                if ($waste->amount !== null) {
                    $amount = (float) $waste->amount;
                } else {
                    $note = ($waste->material?->name ?? '-').': '.$waste->reason;
                    $amount = (float) ($legacyLosses->get($note)?->first()?->amount ?? 0);
                }
            }

            if ($final) {
                $total += (float) $amount;

                if ((float) $amount <= 0) {
                    $unpriced++;
                }
            }

            $wastes[] = [
                'material' => $waste->material?->name ?? '-',
                'unit' => 'pcs',
                'qty' => (int) $waste->qty,
                'reason' => $waste->reason,
                'amount' => $amount,
            ];
        }

        return [
            'final' => $final,
            'bom' => $bom,
            'drylog' => $document->drylog_qty,
            'drylog_name' => DrylogMaterial::find()?->name,
            // Beku saat Lock; NULL = belum dikunci (atau dikunci sebelum drylog dinilai).
            'drylog_amount' => $final && $document->drylog_amount !== null ? (float) $document->drylog_amount : null,
            'wastes' => $wastes,
            'waste_total' => $final ? $total : null,
            'unpriced' => $unpriced,
        ];
    }
}
