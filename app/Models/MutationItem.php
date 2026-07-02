<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class MutationItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mutation_id',
        'barcode',
        'product_id',
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

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(Mutation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    protected static function booted()
    {
        static::deleted(function (MutationItem $item) {
            // Recreate the stock
            $stock = \App\Models\BeefStock::create([
                'barcode' => $item->barcode,
                'product_id' => $item->product_id,
                'warehouse_id' => $item->mutation->from_warehouse_id,
                'grade_id' => $item->grade_id,
                'weight' => $item->weight,
                'qty_pcs' => $item->qty_pcs,
                'ph_level' => $item->ph_level,
                'pack_date' => $item->pack_date,
                'exp_date' => $item->exp_date,
                'origin' => $item->origin,
                'status' => 'IN_STOCK',
                'note' => 'Dikembalikan dari Mutasi Draft',
            ]);

            // Add Stock Movement for cancellation
            \App\Models\BeefStockMovement::create([
                'product_id' => $stock->product_id,
                'warehouse_id' => $stock->warehouse_id,
                'condition' => $stock->grade_id,
                'barcode' => $stock->barcode,
                'transaction_type' => 'MUTATION_CANCEL',
                'reference_document' => $item->mutation->mutation_number,
                'weight_in' => $stock->weight,
                'weight_out' => 0,
                'pcs_in' => $stock->qty_pcs,
                'pcs_out' => 0,
                'note' => 'Barang dihapus dari draft mutasi dan dikembalikan ke stok',
                'created_by' => \Illuminate\Support\Facades\Auth::id() ?? 1,
            ]);
        });
    }
}
