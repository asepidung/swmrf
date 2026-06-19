<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'box',
        'weight',
        'price',
        'discount_percent',
        'discount_rp',
        'amount',
    ];

    protected $casts = [
        'box' => 'integer',
        'weight' => 'float',
        'price' => 'float',
        'discount_percent' => 'float',
        'discount_rp' => 'float',
        'amount' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
