<?php

namespace App\Services;

use App\Models\Boning;
use App\Models\Repack;
use Carbon\CarbonInterface;

/**
 * Laporan pemakaian bahan per PERIODE untuk atasan (issue #509, langkah 5b).
 *
 * Hanya dokumen yang sudah TERKUNCI yang dihitung, supaya angkanya final dan
 * sama dengan Financial Loss; dokumen yang belum terkunci tidak ikut. Tanggal
 * dokumen adalah tanggal boning/repack, batas awal dan akhir ikut dihitung.
 *
 * Setiap dokumen dijumlahkan lewat `ProductionMaterialSummary` -- satu
 * sumber untuk halaman dokumen, cetakan, dan laporan ini -- jadi angkanya
 * tidak bisa berbeda diam-diam dari yang tampil di dokumennya.
 *
 * Tidak menyentuh stok.
 */
class ProductionMaterialReport
{
    /**
     * @return array{
     *     from: string,
     *     until: string,
     *     documents: array<int, array{kind: string, id: int, doc_no: string, date: string, drylog: int|null, drylog_amount: float, waste_qty: int, waste_amount: float, unpriced: int}>,
     *     bom: array<int, array{material: string, qty: int|float}>,
     *     drylog: array{qty: int, amount: float},
     *     wastes: array<int, array{material: string, qty: int, amount: float}>,
     *     waste_total: array{qty: int, amount: float},
     *     unpriced: int,
     * }
     */
    public static function between(CarbonInterface $from, CarbonInterface $until): array
    {
        $from = $from->copy()->startOfDay();
        $until = $until->copy()->endOfDay();

        $documents = [];
        $bom = [];
        $wastes = [];
        $drylog = ['qty' => 0, 'amount' => 0.0];
        $wasteTotal = ['qty' => 0, 'amount' => 0.0];
        $unpriced = 0;

        $collected = collect()
            ->merge(self::locked(Boning::class, 'boning_date', $from, $until)->map(fn (Boning $d): array => [$d, 'boning', $d->boning_date]))
            ->merge(self::locked(Repack::class, 'repack_date', $from, $until)->map(fn (Repack $d): array => [$d, 'repack', $d->repack_date]))
            ->sortBy(fn (array $row): string => $row[2]->format('Y-m-d').'|'.$row[0]->doc_no);

        foreach ($collected as [$document, $kind, $date]) {
            $summary = ProductionMaterialSummary::for($document);

            foreach ($summary['bom'] as $line) {
                $bom[$line['material']] = ($bom[$line['material']] ?? 0) + $line['qty'];
            }

            $documentWasteQty = 0;
            $documentWasteAmount = 0.0;

            foreach ($summary['wastes'] as $line) {
                $wastes[$line['material']]['qty'] = ($wastes[$line['material']]['qty'] ?? 0) + $line['qty'];
                $wastes[$line['material']]['amount'] = ($wastes[$line['material']]['amount'] ?? 0.0) + (float) $line['amount'];

                $documentWasteQty += $line['qty'];
                $documentWasteAmount += (float) $line['amount'];
            }

            $drylogAmount = (float) ($summary['drylog_amount'] ?? 0);
            $drylog['qty'] += (int) ($summary['drylog'] ?? 0);
            $drylog['amount'] += $drylogAmount;
            $wasteTotal['qty'] += $documentWasteQty;
            $wasteTotal['amount'] += $documentWasteAmount;
            $unpriced += $summary['unpriced'];

            $documents[] = [
                'kind' => $kind,
                'id' => $document->id,
                'doc_no' => $document->doc_no,
                'date' => $date->format('Y-m-d'),
                'drylog' => $summary['drylog'],
                'drylog_amount' => $drylogAmount,
                'waste_qty' => $documentWasteQty,
                'waste_amount' => $documentWasteAmount,
                'unpriced' => $summary['unpriced'],
            ];
        }

        ksort($bom);
        ksort($wastes);

        return [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
            'documents' => $documents,
            'bom' => collect($bom)->map(fn ($qty, $material): array => ['material' => $material, 'qty' => $qty])->values()->all(),
            'drylog' => $drylog,
            'wastes' => collect($wastes)->map(fn (array $row, $material): array => ['material' => $material] + $row)->values()->all(),
            'waste_total' => $wasteTotal,
            'unpriced' => $unpriced,
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> */
    private static function locked(string $model, string $dateColumn, CarbonInterface $from, CarbonInterface $until)
    {
        return $model::query()
            ->where('kunci', true)
            // whereDate, bukan whereBetween: tanggal dokumen bisa tersimpan sebagai
            // "2026-10-31 00:00:00", yang jatuh SESUDAH batas "2026-10-31" -- hari
            // terakhir periode tidak akan ikut terhitung.
            ->whereDate($dateColumn, '>=', $from->toDateString())
            ->whereDate($dateColumn, '<=', $until->toDateString())
            ->orderBy($dateColumn)
            ->orderBy('doc_no')
            ->get();
    }
}
