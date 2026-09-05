<?php

namespace App\Services;

use App\Models\StockTake;
use Illuminate\Validation\ValidationException;

class WarehouseFreezeService
{
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
}
