<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id',
        'product_id',
        'price',
        'note',
    ];

    /**
     * Relasi ke entitas induk daftar harga.
     */
    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Relasi ke entitas produk.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
