<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRequisitionItem extends Model
{
    protected $fillable = [
        'product_requisition_id',
        'product_id',
        'qty',
        'price',
        'subtotal',
        'note',
    ];

    public function productRequisition()
    {
        return $this->belongsTo(ProductRequisition::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
