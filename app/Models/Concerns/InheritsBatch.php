<?php

namespace App\Models\Concerns;

use App\Support\BatchLookup;

/**
 * Barang yang berpindah tabel membawa batchnya.
 *
 * Saat baris berbarcode dibuat tanpa `batch_no`, batch dicari dari barcode
 * yang sama di tabel lain (`BatchLookup`). Dengan begitu tempat yang
 * memindahkan stok (tally, retur ke stok, mutasi, opname) tidak perlu
 * mengingat batch satu per satu -- dan tidak ada yang bisa lupa. Tempat
 * yang melahirkan barang dari DOKUMEN INDUK (boning, repack, penerimaan,
 * retur) mengisinya sendiri.
 */
trait InheritsBatch
{
    protected static function bootInheritsBatch(): void
    {
        static::creating(function ($model): void {
            if (blank($model->batch_no) && filled($model->barcode)) {
                $model->batch_no = BatchLookup::forBarcode($model->barcode);
            }
        });
    }
}
