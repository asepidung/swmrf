<?php

namespace App\Support;

use App\Models\BarcodeCost;
use App\Models\BoningItem;
use App\Models\Costing;
use Illuminate\Support\Facades\DB;

/**
 * Satu rumah untuk nilai per barcode (HPP tahap 2, issue #522).
 *
 * Modul lain membaca nilai lewat `for()` / `valueOf()` dan menulisnya lewat
 * metode di sini -- tidak ada yang menyentuh `barcode_costs` langsung.
 *
 * NULL berarti BELUM bernilai dan tidak pernah dianggap nol: `valueOf()`
 * mengembalikan null, bukan 0, supaya laporan bisa memisahkan "belum bernilai"
 * dari "bernilai nol".
 *
 * Penulisan idempoten: menulis nilai yang sama dua kali tidak mengubah apa pun
 * (dan tidak menambah baris activity log).
 */
class BarcodeCosts
{
    /** Batas kedalaman rambatan (relabel berantai); mencegah putaran tak berujung. */
    public const MAX_DEPTH = 10;

    /** Harga pokok per kg sebuah barcode; null = belum bernilai atau tidak dikenal. */
    public static function for(?string $barcode): ?float
    {
        if (blank($barcode)) {
            return null;
        }

        $cost = BarcodeCost::where('barcode', $barcode)->value('cost_per_kg');

        return $cost === null ? null : (float) $cost;
    }

    /** Nilai rupiah $kg kilogram barcode itu; null = belum bernilai. */
    public static function valueOf(?string $barcode, float $kg): ?float
    {
        $cost = self::for($barcode);

        return $cost === null ? null : round($kg * $cost, 2);
    }

    /**
     * Pastikan barcode punya baris (belum bernilai bila baru). Tidak menyentuh
     * baris yang sudah ada -- nilainya tetap.
     */
    public static function ensure(string $barcode, ?int $productId, string $sourceType, ?int $sourceId = null, ?string $sourceBarcode = null): BarcodeCost
    {
        return BarcodeCost::firstOrCreate(
            ['barcode' => $barcode],
            ['product_id' => $productId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'source_barcode' => $sourceBarcode],
        );
    }

    /**
     * Isi atau timpa nilai sebuah barcode. Mengembalikan true bila nilainya
     * benar-benar berubah. Rambatan ke turunan dipanggil terpisah
     * (`propagateFrom`).
     */
    public static function set(string $barcode, ?float $costPerKg, string $sourceType, ?int $sourceId = null, ?string $note = null, ?int $productId = null): bool
    {
        $costPerKg = $costPerKg === null ? null : round($costPerKg, 2);

        $row = BarcodeCost::firstOrNew(['barcode' => $barcode]);
        $row->product_id ??= $productId;

        $unchanged = $row->exists
            && $row->cost_per_kg === $costPerKg
            && $row->source_type === $sourceType
            && $row->source_id === $sourceId;

        if ($unchanged) {
            return false;
        }

        $row->fill([
            'cost_per_kg' => $costPerKg,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'valued_at' => $costPerKg === null ? null : now(),
            'note' => $note,
        ])->save();

        return true;
    }

    /**
     * Costing dikunci (atau dikunci ulang): semua barcode boning lot itu
     * bernilai `hpp_per_kg` produknya -- MUNDUR, termasuk barcode yang sudah
     * terjual atau jadi bahan repack (keputusan Owner 1 dan 2). Nilai lama
     * ditimpa, lalu dirambatkan ke turunannya. Satu transaksi, idempoten.
     *
     * Barcode yang produknya tidak ada di costing ini dibiarkan (hanya dipastikan
     * barisnya ada).
     *
     * @return int jumlah barcode yang nilainya berubah (termasuk turunannya)
     */
    public static function valueBoning(Costing $costing): int
    {
        return DB::transaction(function () use ($costing): int {
            $hpp = $costing->items()->pluck('hpp_per_kg', 'product_id')->map(fn ($v) => (float) $v);
            $changed = 0;

            BoningItem::where('boning_id', $costing->boning_id)
                ->whereNotNull('barcode')
                ->get(['id', 'barcode', 'product_id'])
                ->each(function (BoningItem $item) use ($costing, $hpp, &$changed): void {
                    if (! $hpp->has($item->product_id)) {
                        self::ensure($item->barcode, $item->product_id, BarcodeCost::SOURCE_BONING);

                        return;
                    }

                    if (self::set($item->barcode, $hpp[$item->product_id], BarcodeCost::SOURCE_BONING, $costing->id, $costing->costing_number, $item->product_id)) {
                        $changed++;
                    }

                    $changed += self::propagateFrom($item->barcode);
                });

            return $changed;
        });
    }

    /**
     * Barcode lahir dari relabel: salin nilai barcode asal, dan ingat asalnya
     * untuk rambatan berikutnya.
     */
    public static function relabel(string $oldBarcode, string $newBarcode, ?int $productId): BarcodeCost
    {
        $cost = self::for($oldBarcode);

        return BarcodeCost::updateOrCreate(
            ['barcode' => $newBarcode],
            [
                'product_id' => $productId,
                'cost_per_kg' => $cost,
                'source_type' => BarcodeCost::SOURCE_RELABEL,
                'source_barcode' => $oldBarcode,
                'valued_at' => $cost === null ? null : now(),
            ],
        );
    }

    /**
     * Rambatkan nilai $barcode ke barcode yang lahir darinya lewat relabel
     * (berantai, dibatasi `MAX_DEPTH`).
     *
     * @param  array<int, string>  $visited
     * @return int jumlah turunan yang nilainya berubah
     */
    public static function propagateFrom(string $barcode, int $depth = 0, array $visited = []): int
    {
        if ($depth >= self::MAX_DEPTH || in_array($barcode, $visited, true)) {
            return 0;
        }

        $visited[] = $barcode;
        $cost = self::for($barcode);
        $changed = 0;

        $children = BarcodeCost::where('source_barcode', $barcode)
            ->where('source_type', BarcodeCost::SOURCE_RELABEL)
            ->get();

        foreach ($children as $child) {
            if ($child->cost_per_kg !== $cost) {
                $child->fill(['cost_per_kg' => $cost, 'valued_at' => $cost === null ? null : now()])->save();
                $changed++;
            }

            $changed += self::propagateFrom($child->barcode, $depth + 1, $visited);
        }

        return $changed;
    }
}
