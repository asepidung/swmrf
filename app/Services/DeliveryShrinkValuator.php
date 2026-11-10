<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\FinancialLoss;
use App\Support\BarcodeCosts;

/**
 * Nilai rupiah susut kirim (Owner, 10 November 2026; membalik keputusan 20 September "Rp 0 selamanya").
 *
 * Susut per produk = berat kirim (item DO) - berat terima (item bukti terima), hanya yang positif.
 * Nilainya kg susut x harga pokok per kg produk itu di DO ini: rata-rata tertimbang `barcode_costs` dari
 * SEMUA karton produk itu di tally DO (bukan harga jual: perusahaan kehilangan modalnya, bukan marginnya).
 *
 * Susutnya tidak tahu karton mana yang menyusut, jadi hanya dinilai bila SEMUA karton produk itu sudah
 * bernilai; satu saja yang belum, rupiahnya tidak ditebak (tetap Rp 0, "belum dinilai"), sama dengan
 * aturan susut repack.
 */
class DeliveryShrinkValuator
{
    /**
     * Kg susut per produk: product_id => kg (> 0).
     *
     * @return array<int, float>
     */
    public static function shortfalls(DeliveryOrder $deliveryOrder): array
    {
        $shipped = $deliveryOrder->items()->get(['product_id', 'weight'])->groupBy('product_id')
            ->map(fn ($rows) => (float) $rows->sum('weight'));

        $received = $deliveryOrder->receipt()->first()?->items()->get(['product_id', 'weight'])->groupBy('product_id')
            ->map(fn ($rows) => (float) $rows->sum('weight')) ?? collect();

        $short = [];

        foreach ($received as $productId => $kg) {
            $diff = round(($shipped[$productId] ?? 0.0) - $kg, 2);

            if ($diff > 0) {
                $short[(int) $productId] = $diff;
            }
        }

        return $short;
    }

    /** Harga pokok per kg produk ini di kiriman ini; null bila ada karton yang belum bernilai atau tidak ada tally. */
    public static function costPerKg(DeliveryOrder $deliveryOrder, int $productId): ?float
    {
        $cartons = $deliveryOrder->tally?->items()->where('product_id', $productId)->get(['barcode', 'weight']);

        if (! $cartons || $cartons->isEmpty()) {
            return null;
        }

        $kg = 0.0;
        $value = 0.0;

        foreach ($cartons as $carton) {
            $nilai = BarcodeCosts::valueOf($carton->barcode, (float) $carton->weight);

            if ($nilai === null) {
                return null;
            }

            $kg += (float) $carton->weight;
            $value += $nilai;
        }

        return $kg > 0 ? $value / $kg : null;
    }

    /** Rupiah susut kirim DO ini; null bila ada produk susut yang belum bisa dinilai. */
    public static function amountFor(DeliveryOrder $deliveryOrder): ?float
    {
        $total = 0.0;

        foreach (self::shortfalls($deliveryOrder) as $productId => $kg) {
            $perKg = self::costPerKg($deliveryOrder, $productId);

            if ($perKg === null) {
                return null;
            }

            $total += $kg * $perKg;
        }

        return round($total, 2);
    }

    /** Hitung ulang satu DO yang masih belum dinilai. @return bool true bila rupiahnya terisi */
    public static function revalue(DeliveryOrder $deliveryOrder): bool
    {
        $loss = $deliveryOrder->financialLoss()->first();

        if (! $loss || ! $loss->isNotPricedYet()) {
            return false;
        }

        $amount = self::amountFor($deliveryOrder);

        if ($amount === null || $amount <= 0) {
            return false;
        }

        $loss->update(['amount' => $amount]);

        return true;
    }

    /** Semua susut kirim yang belum dinilai dicoba lagi (dipanggil saat harga pokok baru masuk). @return int jumlah yang terisi */
    public static function revalueAllPending(): int
    {
        $filled = 0;

        FinancialLoss::query()
            ->where('transaction_type', FinancialLoss::SUMBER_SURAT_JALAN)
            ->where('amount', '<=', 0)
            ->where('quantity', '>', 0)
            ->where('lossable_type', (new DeliveryOrder)->getMorphClass())
            ->get()
            ->each(function (FinancialLoss $loss) use (&$filled): void {
                $deliveryOrder = DeliveryOrder::find($loss->lossable_id);

                if ($deliveryOrder && self::revalue($deliveryOrder)) {
                    $filled++;
                }
            });

        return $filled;
    }
}
