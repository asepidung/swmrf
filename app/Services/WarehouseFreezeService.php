<?php

namespace App\Services;

use App\Models\StockTake;
use Illuminate\Validation\ValidationException;

class WarehouseFreezeService
{
    public static bool $bypassed = false;

    /**
     * Check if a warehouse is currently frozen (in the middle of a stock take).
     * Throws an exception if it is frozen.
     *
     * @param int|null $warehouseId
     * @throws ValidationException
     */
    public static function check(?int $warehouseId)
    {
        if (self::$bypassed) {
            return;
        }

        if (!$warehouseId) {
            return;
        }

        $activeOpname = StockTake::where('warehouse_id', $warehouseId)
            ->where('status', 'IN_PROGRESS')
            ->first();

        if ($activeOpname) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Transaksi ditolak: Gudang ini sedang dalam proses Stock Opname (:doc). Harap selesaikan Opname terlebih dahulu.', [
                    'doc' => $activeOpname->document_number
                ])
            ]);
        }
    }
}
