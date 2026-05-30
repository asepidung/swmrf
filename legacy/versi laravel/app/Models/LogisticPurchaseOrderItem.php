<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $logistic_purchase_order_id
 * @property int $logistic_item_id
 * @property numeric $qty
 * @property numeric $price
 * @property numeric $subtotal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LogisticItem $item
 * @property-read \App\Models\LogisticPurchaseOrder $purchaseOrder
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereLogisticItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereLogisticPurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrderItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticPurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LogisticPurchaseOrder::class, 'logistic_purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LogisticItem::class, 'logistic_item_id');
    }
}
