<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseProductItem extends Model
{
    protected $fillable = [
        'purchase_product_id',
        'product_id',
        'qty',
        'price',
        'subtotal',
        'note',
    ];

    public function purchaseProduct()
    {
        return $this->belongsTo(PurchaseProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
