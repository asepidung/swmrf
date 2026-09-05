<?php

namespace App\Services;

use App\Models\MaterialStockTake;
use Illuminate\Validation\ValidationException;

/**
 * Stok material dibekukan selama opname material berjalan.
 *
 * Konsepnya sama dengan `WarehouseFreezeService` di sisi daging. Keputusan
 * Owner, 5 September 2026: "harusnya punya konsep sama dengan stock daging".
 *
 * Alasannya bukan kerapian, melainkan aritmetika. `system_qty` diambil sebagai
 * SNAPSHOT saat dokumen opnamenya dibuat, dan `difference_qty` dihitung
 * terhadap angka itu. Kalau material masuk atau keluar di tengah hitungan,
 * selisih yang tersimpan sudah tidak berarti apa-apa: ia mengukur jarak ke
 * angka yang sudah tidak ada lagi.
 *
 * Sebelum ini sisi material tidak punya pembekuan sama sekali, jadi penerimaan
 * dan pemakaian material tetap berjalan selama opname -- dan tidak ada satu
 * pun gejala yang memberitahu bahwa hitungannya jadi salah.
 */
class MaterialStockFreezeService
{
    /**
     * Dilewati saat opnamenya sendiri yang sedang menerapkan hasil hitungan.
     *
     * WAJIB dikembalikan lewat `finally`. Kalau dikembalikan di baris terakhir
     * blok yang bisa gagal, sekali transaksinya gagal nilainya tidak pernah
     * pulih -- dan karena ini properti STATIS, seluruh sisa permintaan itu
     * berjalan tanpa pembekuan. Persis bentuk yang ditambal di #283.
     */
    public static bool $bypassed = false;

    /**
     * Menolak penulisan stok material selama ada opname yang berjalan.
     *
     * @throws ValidationException
     */
    public static function check(): void
    {
        if (self::$bypassed) {
            return;
        }

        $opname = MaterialStockTake::whereIn('status', MaterialStockTake::STATUS_SEDANG_MENGHITUNG)
            ->first();

        if (! $opname) {
            return;
        }

        throw ValidationException::withMessages([
            'material_id' => __('Rejected: a material stock count (:doc) is in progress. Finish it first.', [
                'doc' => $opname->document_number,
            ]),
        ]);
    }

    /** Menjalankan sesuatu dengan pembekuan dilewati, lalu memulihkannya. */
    public static function bypass(callable $kerja): mixed
    {
        self::$bypassed = true;

        try {
            return $kerja();
        } finally {
            self::$bypassed = false;
        }
    }
}
