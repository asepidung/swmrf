<?php

namespace App\Services;

use App\Models\BarcodeCost;
use App\Models\FinancialLoss;
use App\Models\Product;
use App\Models\Repack;
use App\Models\RepackValuation;
use App\Support\BarcodeCosts;
use Illuminate\Support\Facades\DB;

/**
 * Menilai sebuah repack yang dikunci (HPP tahap 2, issue #522, keputusan Owner 5).
 *
 *  1. Biaya bahan = jumlah (kg bahan x harga pokok per kg barcodenya).
 *  2. Susut = kg bahan - kg hasil (bila > 0), dinilai dengan RATA-RATA harga
 *     pokok bahan (biaya bahan / kg bahan). Nilainya menjadi `amount` baris
 *     Financial Loss repack yang sudah ada.
 *  3. Sisa biaya (biaya bahan - nilai susut) dibagi ke hasil menurut NILAI JUAL
 *     (`ReferencePrice`, harga acuan DIKUNCI di `repack_valuation_prices`):
 *     `cost_per_kg` hasil = net x k, k = sisa biaya / jumlah(net x kg hasil).
 *     Presisi penuh sampai perkalian akhir (hpp.md §11.3); yang dibulatkan hanya
 *     nilai tersimpan per barcode.
 *  4. Ada hasil tanpa harga acuan -> seluruh hasil dibagi menurut BERAT (satu
 *     `cost_per_kg` untuk semua), repack ditandai `needs_review`.
 *  5. Ada bahan yang belum bernilai -> repack `pending`, hasilnya NULL; dihitung
 *     otomatis begitu semua bahannya bernilai (`recalculate`, dipanggil
 *     rambatan `BarcodeCosts::propagateFrom`).
 *
 * Harga acuan dikunci saat repack dikunci (`lock`); penghitungan ulang karena
 * bahannya berubah nilai memakai harga yang sudah terkunci itu -- hanya biaya
 * bahannya yang berubah.
 *
 * Idempoten, dan tidak menyentuh stok.
 */
class RepackValuator
{
    /**
     * Repack baru dikunci: kunci harga acuan, lalu nilai.
     */
    public static function lock(Repack $repack): void
    {
        DB::transaction(function () use ($repack): void {
            $valuation = $repack->valuation()->firstOrNew();
            $valuation->fill(['total_input_kg' => 0, 'status' => RepackValuation::STATUS_PENDING])->save();

            self::lockPrices($valuation, $repack);

            self::compute($repack->fresh(), $valuation, 0, []);
        });
    }

    /**
     * Bahannya berubah nilai: hitung ulang dengan harga acuan yang sudah
     * terkunci. Repack yang dikunci SEBELUM valuasi ada belum punya harga
     * terkunci -- harga dikunci sekarang.
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
                self::lockPrices($valuation, $repack);
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

    /** Kunci harga acuan setiap produk hasil. */
    private static function lockPrices(RepackValuation $valuation, Repack $repack): void
    {
        $valuation->prices()->delete();

        $productIds = $repack->results()->pluck('product_id')->unique();

        foreach (Product::whereIn('id', $productIds)->get() as $product) {
            $reference = ReferencePrice::forProduct($product);

            $valuation->prices()->create([
                'product_id' => $product->id,
                'reference_group_id' => $reference['reference_group_id'],
                'gross_price' => $reference['gross_price'],
                'trading_terms_percent' => $reference['trading_terms_percent'],
                'net_price' => $reference['net_price'],
                'flag' => $reference['flag'],
            ]);
        }
    }

    /**
     * @param  array<int, string>  $visited
     * @return int jumlah barcode yang nilainya berubah
     */
    private static function compute(Repack $repack, RepackValuation $valuation, int $depth, array $visited): int
    {
        $materials = $repack->materials()->get(['barcode', 'weight']);
        $results = $repack->results()->get(['barcode', 'product_id', 'weight']);

        $inputKg = round((float) $materials->sum('weight'), 2);
        $outputKg = round((float) $results->sum('weight'), 2);
        $shrinkKg = max(0.0, round($inputKg - $outputKg, 2));

        $cost = 0.0;
        $unvalued = false;
        foreach ($materials as $material) {
            $perKg = BarcodeCosts::for($material->barcode);

            if ($perKg === null) {
                $unvalued = true;

                continue;
            }

            $cost += (float) $material->weight * $perKg;
        }

        $changed = 0;

        if ($unvalued) {
            $valuation->fill([
                'total_input_kg' => $inputKg, 'total_input_cost' => null, 'shrink_kg' => $shrinkKg,
                'shrink_value' => null, 'ratio_k' => null, 'status' => RepackValuation::STATUS_PENDING,
                'needs_review' => false, 'valued_at' => null,
            ])->save();

            foreach ($results as $result) {
                $changed += self::setResult($repack, $result, null, $depth, $visited);
            }

            self::writeShrinkLoss($repack, 0.0);

            return $changed;
        }

        $averageCost = $inputKg > 0 ? $cost / $inputKg : 0.0;
        $shrinkValueRaw = $shrinkKg * $averageCost;
        $remaining = $cost - $shrinkValueRaw;

        $net = $valuation->prices()->pluck('net_price', 'product_id');
        $needsReview = $results->contains(fn ($result) => $net->get($result->product_id) === null || (float) $net->get($result->product_id) <= 0);

        $ratioK = null;
        $byValue = ! $needsReview;
        $denominator = 0.0;

        if ($byValue) {
            foreach ($results as $result) {
                $denominator += (float) $net->get($result->product_id) * (float) $result->weight;
            }

            $byValue = $denominator > 0;
        }

        $ratioK = $byValue ? $remaining / $denominator : null;

        foreach ($results as $result) {
            $perKg = match (true) {
                $byValue => (float) $net->get($result->product_id) * $ratioK,
                $outputKg > 0 => $remaining / $outputKg,
                default => 0.0,
            };

            $changed += self::setResult($repack, $result, $perKg, $depth, $visited);
        }

        $valuation->fill([
            'total_input_kg' => $inputKg,
            'total_input_cost' => round($cost, 2),
            'shrink_kg' => $shrinkKg,
            'shrink_value' => round($shrinkValueRaw, 2),
            'ratio_k' => $ratioK === null ? null : round($ratioK, 6),
            'status' => RepackValuation::STATUS_VALUED,
            'needs_review' => ! $byValue,
            'valued_at' => now(),
        ])->save();

        self::writeShrinkLoss($repack, round($shrinkValueRaw, 2));

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
