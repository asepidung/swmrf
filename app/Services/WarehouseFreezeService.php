<?php

namespace App\Services;

use App\Models\StockTake;
use Illuminate\Validation\ValidationException;

class WarehouseFreezeService
{
    /**
     * Dilewati saat opnamenya sendiri yang sedang menerapkan hasil hitungan.
     *
     * WAJIB dikembalikan lewat `finally` -- dan cara paling aman untuk itu
     * adalah TIDAK MENYENTUHNYA LANGSUNG, melainkan lewat `bypass()` di
     * bawah. Kalau dikembalikan di baris terakhir sebuah blok yang bisa
     * gagal, sekali transaksinya gagal nilainya tidak pernah pulih -- dan
     * karena ini properti STATIS, seluruh sisa permintaan itu berjalan tanpa
     * pembekuan, tepat pada saat opname sedang berlangsung.
     */
    public static bool $bypassed = false;

    /**
     * Check if ANY warehouse is currently frozen (in the middle of a global stock take).
     * Throws an exception if it is frozen.
     *
     * @param int|null $warehouseId (Kept for backward compatibility, but ignored)
     * @throws ValidationException
     */
    public static function check(?int $warehouseId = null)
    {
        if (self::$bypassed) {
            return;
        }

        $activeOpname = StockTake::where('status', 'IN_PROGRESS')->first();

        if ($activeOpname) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Rejected: a global stock count (:doc) is in progress. Finish it first.', [
                    'doc' => $activeOpname->document_number
                ])
            ]);
        }
    }

    /**
     * Menjalankan sesuatu dengan pembekuan dilewati, lalu memulihkannya.
     *
     * Bentuknya sama persis dengan `MaterialStockFreezeService::bypass()`.
     * Dua sisi yang menyelesaikan persoalan yang sama harus terlihat sama,
     * supaya yang menyalin salah satunya tidak menyalin bentuk yang lebih
     * rapuh.
     */
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
