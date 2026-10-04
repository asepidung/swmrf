<?php

namespace App\Support;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mencari nomor batch sebuah barcode dari tabel-tabel yang menyimpannya.
 *
 * Barcode adalah identitas barang, jadi batch yang sudah tercatat di salah
 * satu tabel (boning, repack, penerimaan, retur, stok, tally, mutasi,
 * opname) berlaku untuk barcode yang sama di tabel lain. Dipakai
 * `InheritsBatch` agar barang yang berpindah tabel tidak kehilangan
 * batchnya, dan oleh jalur yang membuat barcode BARU dari barang yang
 * barcode lamanya diketahui (relabel Tally, Found Item/Label Rusak).
 */
class BatchLookup
{
    /** Urutan pencarian: tabel hidup dulu, lalu tabel sumber. */
    private const MODELS = [
        \App\Models\BeefStock::class,
        \App\Models\TallyItem::class,
        \App\Models\MutationItem::class,
        \App\Models\StockTakeItem::class,
        \App\Models\BoningItem::class,
        \App\Models\RepackResult::class,
        \App\Models\GoodsReceiptProductItem::class,
        \App\Models\SalesReturnItem::class,
    ];

    public static function forBarcode(?string $barcode): ?string
    {
        if (blank($barcode)) {
            return null;
        }

        foreach (self::MODELS as $model) {
            $query = in_array(SoftDeletes::class, class_uses_recursive($model), true)
                ? $model::withTrashed()
                : $model::query();

            $batch = $query->where('barcode', $barcode)->whereNotNull('batch_no')->value('batch_no');

            if (filled($batch)) {
                return $batch;
            }
        }

        return null;
    }

    /** @return array<int, class-string> */
    public static function models(): array
    {
        return self::MODELS;
    }
}
