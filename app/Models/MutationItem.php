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
            // When an item is removed from a draft mutation, we unlock it in beef_stocks
            $stock = BeefStock::where('barcode', $item->barcode)->where('status', 'IN_MUTATION')->first();
            if ($stock) {
                $stock->status = 'IN_STOCK';
                $stock->save();
            }
        });
    }
}
