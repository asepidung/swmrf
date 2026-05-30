<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $beef_purchase_order_id
 * @property int $product_id
 * @property numeric $qty
 * @property numeric $price
 * @property numeric $subtotal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\BeefPurchaseOrder $purchaseOrder
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereBeefPurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrderItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BeefPurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'beef_purchase_order_id',
        'product_id',
        'qty',
        'price',
        'subtotal',
    ];

    /* Relasi balik ke PO Header */
    public function purchaseOrder()
    {
        return $this->belongsTo(BeefPurchaseOrder::class, 'beef_purchase_order_id');
    }

    /* Relasi ke produk (Bawaan asli lu) */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /* --- TAMBAHAN: Alias Relasi 'item' Biar Form GR Nggak Error --- */
    public function item()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
