<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Harga patokan costing per produk (gross + net), issue #541. Bukan price list penjualan:
 * hanya cadangan untuk produk yang belum pernah ada di costing terkunci, dan diperbarui
 * setiap costing dikunci.
 */
class CostingPriceDefault extends Model
{
    protected $fillable = ['product_id', 'gross_price', 'net_price', 'source'];

    protected $casts = [
        'gross_price' => 'float',
        'net_price' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
