<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'product_id',
        'warehouse_id',
        'grade_id',
        'barcode',
        'weight',
        'qty_pcs',
        'unit_price',
        'line_amount',
        'ph_level',
        'pack_date',
        'exp_date',
        'origin',
        'is_repacked',
    ];

    protected $casts = [
        'weight' => 'float',
        'qty_pcs' => 'integer',
        'unit_price' => 'decimal:2',
        'line_amount' => 'decimal:2',
        'ph_level' => 'float',
        'pack_date' => 'date',
        'exp_date' => 'date',
        'is_repacked' => 'boolean',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
