<?php

namespace App\Services;

use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockService
{
    /**
     * Adjust the stock of a material and log the movement.
     *
     * @param int $materialId
     * @param float $qtyDelta
     * @param string $transactionType
     * @param string $referenceDocument
     * @param string|null $note
     * @param int|null $createdBy
     * @return MaterialStock|null
     */
    public static function adjustStock(
        int $materialId,
        float $qtyDelta,
        string $transactionType,
        string $referenceDocument,
        ?string $note = null,
        ?int $createdBy = null
    ): ?MaterialStock {
        if ($qtyDelta == 0) {
            return null;
        }

        return DB::transaction(function () use ($materialId, $qtyDelta, $transactionType, $referenceDocument, $note, $createdBy) {
            // Retrieve current stock
            $stock = MaterialStock::where('material_id', $materialId)->lockForUpdate()->first();
            $currentQty = $stock ? (float) $stock->qty : 0.0;
            $newQty = $currentQty + $qtyDelta;

            // Determine Qty In / Qty Out
            $qtyIn = $qtyDelta > 0 ? $qtyDelta : 0.0;
            $qtyOut = $qtyDelta < 0 ? abs($qtyDelta) : 0.0;

            // Update or Create stock record
            if ($stock) {
                $stock->update(['qty' => $newQty]);
            } else {
                $stock = MaterialStock::create([
                    'material_id' => $materialId,
                    'qty' => $newQty,
                ]);
            }

            // Create movement ledger record
            MaterialStockMovement::create([
                'material_id' => $materialId,
                'transaction_type' => $transactionType,
                'reference_document' => $referenceDocument,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'balance' => $newQty,
                'note' => $note,
                'created_by' => $createdBy ?? Auth::id() ?? 1,
            ]);

            return $stock;
        });
    }
}
