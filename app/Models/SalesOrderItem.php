<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'weight',
        'price',
        'discount',
        'note',
        'spec',
    ];

    /**
     * Salinan spek customer saat baris ini dibuat (#579). Tidak mengikuti
     * spek tetap sesudahnya, supaya SO lama tidak berubah.
     */
    protected $casts = [
        'spec' => 'array',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
