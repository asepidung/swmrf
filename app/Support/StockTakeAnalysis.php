<?php

namespace App\Support;

use App\Models\StockTake;
use Illuminate\Support\Collection;

/**
 * Bahan laporan analisa opname daging: ringkasan angka, nilai rupiah (HPP per
 * barcode), dan pencocokan barang hilang dengan barang temuan.
 *
 * Hanya MEMBACA. Tidak mengubah stok, status, maupun nilai barcode.
 *
 * Pasangan hilang-temuan adalah DUGAAN dari data (produk dan berat sama persis),
 * bukan bukti: bisa jadi karton yang sama dengan label berganti, perlu dicek
 * fisik oleh gudang.
 */
class StockTakeAnalysis
{
    /** @return array<string, mixed> */
    public static function for(StockTake $record): array
    {
        $items = $record->items()->with('product')->get();

        $matched = $items->where('status', 'MATCHED');
        $missing = $items->where('status', 'MISSING')->values();
        $found = $items->where('status', 'UNEXPECTED')->values();

        // HPP barang hilang: nilai barcode-nya sendiri.
        $missingCost = [];
        foreach ($missing as $item) {
            $missingCost[$item->id] = BarcodeCosts::valueOf($item->barcode, (float) $item->weight);
        }

        // Rata-rata HPP per kg barang hilang menurut produk, untuk menilai temuan yang belum punya HPP.
        $avgByProduct = $missing->groupBy('product_id')->map(function (Collection $rows) use ($missingCost) {
            $kg = $rows->filter(fn ($r) => $missingCost[$r->id] !== null)->sum('weight');
            $rp = $rows->sum(fn ($r) => $missingCost[$r->id] ?? 0);

            return $kg > 0 ? $rp / $kg : null;
        });

        $foundCost = [];
        $foundEstimated = [];
        foreach ($found as $item) {
            $value = BarcodeCosts::valueOf($item->barcode, (float) $item->weight);
            $estimated = false;
            if ($value === null && ($avg = $avgByProduct[$item->product_id] ?? null) !== null) {
                $value = round($avg * (float) $item->weight, 2);
                $estimated = true;
            }
            $foundCost[$item->id] = $value;
            $foundEstimated[$item->id] = $estimated;
        }

        // Pasangan: produk dan berat (2 desimal) sama persis, satu lawan satu.
        $foundPool = $found->groupBy(fn ($r) => $r->product_id.'|'.number_format((float) $r->weight, 2, '.', ''))->map->values();
        $pairs = [];
        $pairedMissing = [];
        $pairedFound = [];
        foreach ($missing as $m) {
            $key = $m->product_id.'|'.number_format((float) $m->weight, 2, '.', '');
            $candidates = $foundPool[$key] ?? collect();
            $f = $candidates->first(fn ($c) => ! isset($pairedFound[$c->id]));
            if (! $f) {
                continue;
            }
            $pairedMissing[$m->id] = true;
            $pairedFound[$f->id] = true;
            $pairs[] = [
                'missing' => $m,
                'found' => $f,
                'same_pack_date' => $m->pack_date && $f->pack_date && $m->pack_date->isSameDay($f->pack_date),
                'value' => $missingCost[$m->id],
            ];
        }

        $unpairedMissing = $missing->reject(fn ($m) => isset($pairedMissing[$m->id]))
            ->sortByDesc(fn ($m) => $missingCost[$m->id] ?? 0)->values();
        $unpairedFound = $found->reject(fn ($f) => isset($pairedFound[$f->id]))
            ->sortByDesc(fn ($f) => $foundCost[$f->id] ?? 0)->values();

        $sum = fn (Collection $rows, array $costs) => $rows->sum(fn ($r) => $costs[$r->id] ?? 0);

        // Ringkasan per produk.
        $perProduct = [];
        foreach ($items as $item) {
            $k = $item->product_id ?? 0;
            $perProduct[$k] ??= ['product' => $item->product?->name ?? '-', 'matched' => 0.0, 'missing' => 0.0, 'found' => 0.0, 'missing_value' => 0.0, 'found_value' => 0.0];
            $w = (float) $item->weight;
            match ($item->status) {
                'MATCHED' => $perProduct[$k]['matched'] += $w,
                'MISSING' => [$perProduct[$k]['missing'] += $w, $perProduct[$k]['missing_value'] += $missingCost[$item->id] ?? 0],
                'UNEXPECTED' => [$perProduct[$k]['found'] += $w, $perProduct[$k]['found_value'] += $foundCost[$item->id] ?? 0],
                default => null,
            };
        }
        uasort($perProduct, fn ($a, $b) => abs($b['found_value'] - $b['missing_value']) <=> abs($a['found_value'] - $a['missing_value']));

        $matchedKg = (float) $matched->sum('weight');
        $missingKg = (float) $missing->sum('weight');
        $foundKg = (float) $found->sum('weight');
        $missingValue = (float) $sum($missing, $missingCost);
        $foundValue = (float) $sum($found, $foundCost);
        $pairedKg = (float) collect($pairs)->sum(fn ($p) => (float) $p['missing']->weight);
        $pairedValue = (float) collect($pairs)->sum(fn ($p) => $p['value'] ?? 0);

        return [
            'record' => $record,
            'matched_count' => $matched->count(),
            'matched_kg' => $matchedKg,
            'missing' => $missing,
            'missing_count' => $missing->count(),
            'missing_kg' => $missingKg,
            'missing_value' => $missingValue,
            'missing_cost' => $missingCost,
            'found' => $found,
            'found_count' => $found->count(),
            'found_kg' => $foundKg,
            'found_value' => $foundValue,
            'found_cost' => $foundCost,
            'found_estimated' => $foundEstimated,
            'found_estimated_count' => count(array_filter($foundEstimated)),
            'missing_unvalued_count' => count(array_filter($missingCost, fn ($v) => $v === null)),
            'found_manual_count' => $found->where('is_manual', true)->count(),
            'system_kg' => $matchedKg + $missingKg,
            'physical_kg' => $matchedKg + $foundKg,
            'net_kg' => $foundKg - $missingKg,
            'net_value' => $foundValue - $missingValue,
            'pairs' => $pairs,
            'pair_count' => count($pairs),
            'pair_kg' => $pairedKg,
            'pair_value' => $pairedValue,
            'pair_same_pack_date' => collect($pairs)->where('same_pack_date', true)->count(),
            'unpaired_missing' => $unpairedMissing,
            'unpaired_missing_kg' => (float) $unpairedMissing->sum('weight'),
            'unpaired_missing_value' => (float) $sum($unpairedMissing, $missingCost),
            'unpaired_found' => $unpairedFound,
            'unpaired_found_kg' => (float) $unpairedFound->sum('weight'),
            'unpaired_found_value' => (float) $sum($unpairedFound, $foundCost),
            'per_product' => $perProduct,
        ];
    }
}
