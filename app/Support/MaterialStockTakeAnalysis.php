<?php

namespace App\Support;

use App\Models\MaterialStockTake;

/**
 * Bahan laporan analisa opname material: ringkasan selisih, per kategori,
 * barang yang paling menyimpang, barang yang belum dihitung, dan barang yang
 * pernah diminta hitung ulang.
 *
 * Hanya MEMBACA. Material tidak punya harga pokok, jadi laporan ini dalam
 * JUMLAH, tidak dalam rupiah, dan jumlah tidak dijumlahkan lintas satuan.
 */
class MaterialStockTakeAnalysis
{
    /** @return array<string, mixed> */
    public static function for(MaterialStockTake $record): array
    {
        $items = $record->items()->with(['material.unit', 'material.category'])->get();

        $baris = $items->map(function ($i) {
            $sistem = (float) $i->system_qty;
            $fisik = $i->physical_qty === null ? null : (float) $i->physical_qty;
            $selisih = $fisik === null ? null : $fisik - $sistem;

            return [
                'item' => $i,
                'name' => $i->material?->name ?? '-',
                'code' => $i->material?->code,
                'unit' => $i->material?->unit?->name ?? '',
                'category' => $i->material?->category?->name ?? 'Tanpa kategori',
                'system' => $sistem,
                'physical' => $fisik,
                'diff' => $selisih,
                'pct' => $selisih !== null && $sistem > 0 ? $selisih / $sistem * 100 : null,
            ];
        })->sortBy('name')->values();

        $dihitung = $baris->filter(fn ($b) => $b['physical'] !== null)->values();
        $belum = $baris->filter(fn ($b) => $b['physical'] === null)->values();
        $sesuai = $dihitung->filter(fn ($b) => abs($b['diff']) < 0.0001)->values();
        $lebih = $dihitung->filter(fn ($b) => $b['diff'] > 0.0001)->sortByDesc('diff')->values();
        $kurang = $dihitung->filter(fn ($b) => $b['diff'] < -0.0001)->sortBy('diff')->values();

        // Per kategori: jumlah barang, yang sesuai/lebih/kurang.
        $perKategori = $baris->groupBy('category')->map(function ($rows, $nama) {
            $d = $rows->filter(fn ($b) => $b['physical'] !== null);

            return [
                'category' => $nama,
                'total' => $rows->count(),
                'counted' => $d->count(),
                'matches' => $d->filter(fn ($b) => abs($b['diff']) < 0.0001)->count(),
                'over' => $d->filter(fn ($b) => $b['diff'] > 0.0001)->count(),
                'short' => $d->filter(fn ($b) => $b['diff'] < -0.0001)->count(),
            ];
        })->sortKeys()->values();

        // Total selisih per satuan (tidak dijumlahkan lintas satuan).
        $perSatuan = $dihitung->groupBy('unit')->map(fn ($rows, $unit) => [
            'unit' => $unit === '' ? '-' : $unit,
            'over' => (float) $rows->where('diff', '>', 0.0001)->sum('diff'),
            'short' => (float) $rows->where('diff', '<', -0.0001)->sum('diff'),
        ])->sortKeys()->values();

        // Barang yang pernah diminta hitung ulang (riwayat activity log) beserta alasannya.
        $hitungUlang = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', \App\Models\MaterialStockTakeItem::class)
            ->whereIn('subject_id', $items->pluck('id'))
            ->where('description', 'Recount requested')
            ->get()
            ->map(function ($log) use ($baris) {
                $b = $baris->first(fn ($x) => $x['item']->id === $log->subject_id);

                return [
                    'name' => $b['name'] ?? '-',
                    'unit' => $b['unit'] ?? '',
                    'previous' => $log->properties['previous_physical_qty'] ?? null,
                    'now' => $b['physical'] ?? null,
                    'note' => $log->properties['note'] ?? null,
                ];
            })->sortBy('name')->values();

        return [
            'record' => $record,
            'rows' => $baris,
            'total' => $baris->count(),
            'counted' => $dihitung->count(),
            'not_counted' => $belum,
            'matches' => $sesuai,
            'over' => $lebih,
            'short' => $kurang,
            'per_category' => $perKategori,
            'per_unit' => $perSatuan,
            'recounts' => $hitungUlang,
            'accuracy_pct' => $dihitung->count() > 0 ? $sesuai->count() / $dihitung->count() * 100 : null,
        ];
    }
}
