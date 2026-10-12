<?php

namespace App\Services;

use App\Models\BarcodeCost;
use App\Models\FinancialLoss;
use App\Models\Repack;
use App\Models\RepackValuation;
use App\Support\BarcodeCosts;
use Illuminate\Support\Facades\DB;

/**
 * Menilai sebuah repack yang dikunci -- ATURAN FINAL (keputusan Owner, 12 Oktober
 * 2026, issue #522; menggantikan bagi-nilai-jual). Prinsip: hasil akhir harus
 * cocok dengan bahan, kilo DAN rupiah.
 *
 * Per produk i: `in_i` kg bahan, `out_i` kg hasil produk yang sama, `c_i` rata-rata
 * tertimbang `cost_per_kg` barcode bahan produk i.
 *
 *  1. IKUT PRODUK: `pass_i = min(in_i, out_i)` kg hasil membawa `c_i`.
 *  2. SUSUT = jumlah in - jumlah out (bila > 0) -> `amount` Financial Loss repack
 *     (#385) = susut x rata-rata harga pokok SELURUH bahan.
 *  3. PRODUK BARU / KILO KELEBIHAN (`out_i - pass_i`, termasuk produk yang tidak ada
 *     di bahan) -> nilainya rata-rata tertimbang `cost_per_kg` produk itu di stok
 *     (barcode bernilai) saat repack dikunci, DI-SNAPSHOT. Tidak ada -> rata-rata
 *     harga pokok bahan + `needs_review`.
 *  4. SELISIH = jumlah (in_i - pass_i) x c_i (biaya kilo bahan yang terpakai/berubah)
 *     - nilai produk baru - nilai susut. TIDAK jadi Financial Loss. Dibagi ke produk
 *     bahan menurut porsi biaya terpakainya: `share_i = selisih x terpakai_i / jumlah
 *     terpakai`.
 *       - produk i masih punya hasil (`pass_i > 0`): `share_i` ditambahkan ke nilai
 *         produk itu ("ditanggung produk yang disesetan");
 *       - produk i habis terpakai (`pass_i = 0`): `share_i` dibagi ke produk baru /
 *         kelebihan menurut kg.
 *     Cost per kg hasil = nilai produk / `out_i`, sama untuk semua barcode produk itu.
 *  5. PEMERIKSAAN: jumlah nilai hasil + nilai susut = jumlah biaya bahan (toleransi
 *     pembulatan); kalau meleset, `needs_review`.
 *
 * `needs_review` juga bila: ada nilai stok produk baru yang tidak ada (memakai
 * rata-rata bahan), ada cost per kg yang jadi <= 0, atau selisih tidak punya tempat
 * (tidak ada produk baru/kelebihan untuk menampung bagian produk yang habis -- dibagi
 * ke semua hasil menurut kg).
 *
 * Bahan yang belum bernilai -> `pending`, hasilnya NULL; dihitung otomatis begitu
 * semua bahannya bernilai (`recalculate`, dipanggil rambatan `BarcodeCosts::propagateFrom`).
 * Nilai stok produk baru dikunci saat repack dikunci (`lock`); hitung ulang karena
 * bahan berubah nilai memakai snapshot itu.
 *
 * Idempoten, dan tidak menyentuh stok.
 */
class RepackValuator
{
    /** Repack baru dikunci: snapshot nilai stok produk baru, lalu nilai. */
    public static function lock(Repack $repack): void
    {
        DB::transaction(function () use ($repack): void {
            $valuation = $repack->valuation()->firstOrNew();
            $valuation->fill(['total_input_kg' => 0, 'status' => RepackValuation::STATUS_PENDING])->save();

            self::snapshotStockCosts($valuation, $repack->fresh());

            self::compute($repack->fresh(), $valuation->fresh(), 0, []);
        });
    }

    /**
     * Bahannya berubah nilai: hitung ulang dengan snapshot nilai stok yang sudah
     * terkunci. Repack yang dikunci SEBELUM valuasi ada belum punya snapshot --
     * dibuat sekarang.
     *
     * @param  array<int, string>  $visited
     * @return int jumlah barcode yang nilainya berubah
     */
    public static function recalculate(Repack $repack, int $depth = 0, array $visited = []): int
    {
        if (! $repack->kunci) {
            return 0;
        }

        return DB::transaction(function () use ($repack, $depth, $visited): int {
            $valuation = $repack->valuation;

            if (! $valuation) {
                $valuation = $repack->valuation()->create(['total_input_kg' => 0, 'status' => RepackValuation::STATUS_PENDING]);
                self::snapshotStockCosts($valuation, $repack);
            }

            return self::compute($repack, $valuation, $depth, $visited);
        });
    }

    /**
     * Repack dibuka kuncinya: hasilnya kembali belum bernilai, dan turunannya
     * (relabel, repack berikutnya) ikut.
     */
    public static function release(Repack $repack): void
    {
        DB::transaction(function () use ($repack): void {
            foreach ($repack->results()->get(['barcode', 'product_id']) as $result) {
                BarcodeCosts::set($result->barcode, null, BarcodeCost::SOURCE_REPACK, $repack->id, $repack->doc_no, $result->product_id);
                BarcodeCosts::propagateFrom($result->barcode, 1);
            }

            $repack->valuation()->delete();
        });
    }

    /**
     * Kilo hasil per produk yang melebihi kilo bahannya produk itu (produk baru, atau
     * kelebihan) -- hanya soal kilo, tidak butuh nilai.
     *
     * @return array<int, float> product_id => kg kelebihan
     */
    private static function excessKg(Repack $repack): array
    {
        $in = $repack->materials()->get(['product_id', 'weight'])->groupBy('product_id')->map(fn ($g) => (float) $g->sum('weight'));
        $excess = [];

        foreach ($repack->results()->get(['product_id', 'weight'])->groupBy('product_id') as $productId => $group) {
            $over = round((float) $group->sum('weight') - min((float) ($in[$productId] ?? 0), (float) $group->sum('weight')), 4);

            if ($over > 0) {
                $excess[(int) $productId] = $over;
            }
        }

        return $excess;
    }

    /**
     * Snapshot nilai stok tiap produk baru / kelebihan: rata-rata tertimbang
     * `cost_per_kg` produk itu di stok saat ini, TANPA hasil repack ini sendiri.
     */
    private static function snapshotStockCosts(RepackValuation $valuation, Repack $repack): void
    {
        $valuation->prices()->delete();

        $ownBarcodes = $repack->results()->pluck('barcode')->all();

        foreach (array_keys(self::excessKg($repack)) as $productId) {
            $valuation->prices()->create([
                'product_id' => $productId,
                'stock_cost_per_kg' => ($average = BarcodeCosts::averageInStock($productId, $ownBarcodes)) === null ? null : round($average, 2),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $visited
     * @return int jumlah barcode yang nilainya berubah
     */
    private static function compute(Repack $repack, RepackValuation $valuation, int $depth, array $visited): int
    {
        $materials = $repack->materials()->get(['barcode', 'weight', 'product_id']);
        $results = $repack->results()->get(['barcode', 'weight', 'product_id']);

        $inKg = [];
        $inCost = [];
        $unvalued = false;

        foreach ($materials as $material) {
            $perKg = BarcodeCosts::for($material->barcode);
            $pid = (int) $material->product_id;
            $inKg[$pid] = ($inKg[$pid] ?? 0.0) + (float) $material->weight;

            if ($perKg === null) {
                $unvalued = true;

                continue;
            }

            $inCost[$pid] = ($inCost[$pid] ?? 0.0) + (float) $material->weight * $perKg;
        }

        $outKg = [];
        foreach ($results as $result) {
            $outKg[(int) $result->product_id] = ($outKg[(int) $result->product_id] ?? 0.0) + (float) $result->weight;
        }

        $totalIn = round(array_sum($inKg), 2);
        $totalOut = round(array_sum($outKg), 2);
        $shrinkKg = max(0.0, round($totalIn - $totalOut, 2));
        $changed = 0;

        if ($unvalued) {
            $valuation->fill([
                'total_input_kg' => $totalIn, 'total_input_cost' => null, 'shrink_kg' => $shrinkKg,
                'shrink_value' => null, 'new_product_value' => null, 'difference_value' => null,
                'status' => RepackValuation::STATUS_PENDING, 'needs_review' => false, 'valued_at' => null,
            ])->save();

            foreach ($results as $result) {
                $changed += self::setResult($repack, $result, null, $depth, $visited);
            }

            self::writeShrinkLoss($repack, 0.0);

            return $changed;
        }

        $totalCost = array_sum($inCost);
        $averageAll = $totalIn > 0 ? $totalCost / $totalIn : 0.0;
        $needsReview = false;

        // 1. ikut produk, 3. nilai produk baru / kelebihan
        $pass = [];
        $excess = [];
        $value = [];
        $newValue = 0.0;
        $snapshot = $valuation->prices->pluck('stock_cost_per_kg', 'product_id');

        foreach ($outKg as $pid => $out) {
            $in = $inKg[$pid] ?? 0.0;
            $c = $in > 0 ? ($inCost[$pid] ?? 0.0) / $in : 0.0;
            $pass[$pid] = min($in, $out);
            $excess[$pid] = max(0.0, $out - $pass[$pid]);

            $stockCost = $snapshot->get($pid);
            if ($excess[$pid] > 0 && $stockCost === null) {
                $stockCost = $averageAll;
                $needsReview = true;
            }

            $excessValue = $excess[$pid] > 0 ? $excess[$pid] * (float) $stockCost : 0.0;
            $newValue += $excessValue;
            $value[$pid] = $pass[$pid] * $c + $excessValue;
        }

        // 2. susut
        $shrinkValue = $shrinkKg * $averageAll;

        // 4. selisih dan porsinya
        $used = [];
        foreach ($inKg as $pid => $in) {
            $c = $in > 0 ? ($inCost[$pid] ?? 0.0) / $in : 0.0;
            $used[$pid] = ($in - ($pass[$pid] ?? 0.0)) * $c;
        }
        $usedTotal = array_sum($used);
        $difference = $usedTotal - $newValue - $shrinkValue;

        $pool = 0.0;
        if ($usedTotal > 0) {
            foreach ($used as $pid => $usedValue) {
                $share = $difference * $usedValue / $usedTotal;

                if (($pass[$pid] ?? 0.0) > 0) {
                    $value[$pid] += $share;
                } else {
                    $pool += $share;
                }
            }
        } else {
            $pool = $difference;
        }

        if (abs($pool) > 0.000001) {
            $targets = array_filter($excess, fn ($kg) => $kg > 0);

            if ($targets === []) {
                $targets = $outKg;
                $needsReview = true;
            }

            $targetKg = array_sum($targets);
            foreach ($targets as $pid => $kg) {
                $value[$pid] += $targetKg > 0 ? $pool * $kg / $targetKg : 0.0;
            }
        }

        // nilai per kg tiap produk hasil, lalu tulis ke barcodenya
        $reconciled = round($shrinkValue, 2);
        foreach ($results as $result) {
            $pid = (int) $result->product_id;
            $perKg = $outKg[$pid] > 0 ? $value[$pid] / $outKg[$pid] : 0.0;

            if ($perKg <= 0) {
                $needsReview = true;
            }

            $reconciled += (float) $result->weight * round($perKg, 2);
            $changed += self::setResult($repack, $result, $perKg, $depth, $visited);
        }

        // 5. pemeriksaan: nilai hasil + susut = biaya bahan (toleransi pembulatan sen per kg)
        if (abs($reconciled - $totalCost) > 1 + 0.005 * $totalOut) {
            $needsReview = true;
        }

        $valuation->fill([
            'total_input_kg' => $totalIn,
            'total_input_cost' => round($totalCost, 2),
            'shrink_kg' => $shrinkKg,
            'shrink_value' => round($shrinkValue, 2),
            'new_product_value' => round($newValue, 2),
            'difference_value' => round($difference, 2),
            'status' => RepackValuation::STATUS_VALUED,
            'needs_review' => $needsReview,
            'valued_at' => now(),
        ])->save();

        self::writeShrinkLoss($repack, round($shrinkValue, 2));

        return $changed;
    }

    /**
     * Nilai satu barcode hasil, lalu rambatkan ke turunannya (relabel dan repack
     * berikutnya). Kedalaman dan putaran dijaga oleh `BarcodeCosts::propagateFrom`.
     *
     * @param  array<int, string>  $visited
     */
    private static function setResult(Repack $repack, $result, ?float $perKg, int $depth, array $visited): int
    {
        $changed = BarcodeCosts::set($result->barcode, $perKg, BarcodeCost::SOURCE_REPACK, $repack->id, $repack->doc_no, $result->product_id) ? 1 : 0;

        return $changed + BarcodeCosts::propagateFrom($result->barcode, $depth + 1, $visited);
    }

    /** Nilai susut menjadi `amount` baris Financial Loss repack (bila barisnya ada). */
    private static function writeShrinkLoss(Repack $repack, float $amount): void
    {
        $repack->financialLoss()
            ->where('transaction_type', FinancialLoss::SUMBER_REPACK)
            ->get()
            ->each(fn (FinancialLoss $loss) => $loss->update(['amount' => $amount]));
    }
}
