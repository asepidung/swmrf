<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot nilai stok sebuah produk baru / kilo kelebihan pada saat sebuah repack
 * dikunci (HPP tahap 2, aturan final 12 Oktober 2026). `stock_cost_per_kg` null =
 * tidak ada stok bernilai saat itu. Lihat `RepackValuation`.
 */
class RepackValuationPrice extends Model
{
    protected $fillable = ['repack_valuation_id', 'product_id', 'stock_cost_per_kg'];

    protected $casts = ['stock_cost_per_kg' => 'float'];

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(RepackValuation::class, 'repack_valuation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
