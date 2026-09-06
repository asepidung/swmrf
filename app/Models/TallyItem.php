<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class TallyItem extends Model
{
    protected $table = 'tally_items';

    protected $fillable = [
        'tally_id',
        'barcode',
        'product_id',
        'warehouse_id',
        'grade_id',
        'weight',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
        'origin',
    ];

    protected $casts = [
        'weight' => 'float',
        'qty_pcs' => 'integer',
        'ph_level' => 'float',
        'pack_date' => 'date',
        'exp_date' => 'date',
    ];

    public function tally(): BelongsTo
    {
        return $this->belongsTo(Tally::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    protected static function booted()
    {
        static::deleted(function (TallyItem $item) {
            $tally = $item->tally;
            $do = $tally ? \App\Models\DeliveryOrder::where('tally_id', $tally->id)->first() : null;

            $note = $do 
                ? 'Tolakan dari DO#' . $do->delivery_order_number 
                : 'Unscan / Return to stock from Tally';
            
            $refDoc = $do 
                ? $do->delivery_order_number 
                : ($tally?->tally_number ?? 'Tally rollback');

            $txType = $do ? 'DO_REJECT' : 'TALLY_REVERT';

            // Restore to beef_stocks
            BeefStock::create([
                'barcode' => $item->barcode,
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'grade_id' => $item->grade_id,
                'weight' => $item->weight,
                'qty_pcs' => $item->qty_pcs,
                'ph_level' => $item->ph_level,
                'pack_date' => $item->pack_date,
                'exp_date' => $item->exp_date,
                'origin' => $item->origin,
                'status' => 'IN_STOCK',
                'note' => $note,
            ]);

            // Log beef stock movement
            BeefStockMovement::create([
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'condition' => $item->grade_id, // condition stores grade_id
                'barcode' => $item->barcode,
                'transaction_type' => $txType,
                'reference_document' => $refDoc,
                'weight_in' => $item->weight,
                'weight_out' => 0,
                'pcs_in' => $item->qty_pcs,
                'pcs_out' => 0,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);
        });
    }
}
