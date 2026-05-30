<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeefStockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'grade_id',
        'barcode',
        'transaction_type',
        'reference_document',
        'weight_in',
        'weight_out',
        'pcs_in',
        'pcs_out',
        'note',
        'created_by'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
