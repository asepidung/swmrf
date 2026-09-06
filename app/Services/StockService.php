<?php

namespace App\Services;

use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

            // Stok TIDAK BOLEH turun di bawah nol.
            //
            // Keputusan Owner, 6 September 2026. Sebelum ini tidak ada batas
            // bawah sama sekali: menghapus dokumen temuan yang materialnya
            // sudah terpakai, atau membatalkan pemakaian yang barangnya sudah
            // habis, langsung mendorong saldonya menjadi minus.
            //
            // Stok minus tidak pernah berarti "gudangnya berhutang barang".
            // Ia selalu berarti ADA YANG SALAH DICATAT -- dan begitu tersimpan,
            // yang salah itu ikut mengalir ke laporan, ke opname, dan ke
            // penilaian persediaan, tanpa satu pun gejala yang memberitahu.
            //
            // Ditolak dengan menyebut sisa yang sebenarnya, supaya yang
            // membacanya tahu berapa yang masih ada.
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'qty' => __('Stock would go below zero: :material has :available left, this movement asks for :requested.', [
                        'material' => $stock?->material?->name ?? ('#'.$materialId),
                        'available' => number_format($currentQty, 2, ',', '.'),
                        'requested' => number_format(abs($qtyDelta), 2, ',', '.'),
                    ]),
                ]);
            }

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
                'created_by' => $createdBy ?? Auth::id(),
            ]);

            return $stock;
        });
    }
}
