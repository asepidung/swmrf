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
    ];

    /**
     * Relasi kembali ke entitas induk pesanan.
     */
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Relasi ke entitas produk.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
