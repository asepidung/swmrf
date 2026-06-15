<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderReceiptItem extends Model
{
    protected $table = 'delivery_order_receipt_items';

    protected $fillable = [
        'delivery_order_receipt_id',
        'product_id',
        'box',
        'weight',
        'notes',
    ];

    protected $casts = [
        'box' => 'integer',
        'weight' => 'float',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderReceipt::class, 'delivery_order_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
