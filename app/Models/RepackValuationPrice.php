<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Harga acuan per produk hasil yang dikunci saat sebuah repack dikunci
 * (HPP tahap 2, issue #522). Lihat `RepackValuation`.
 */
class RepackValuationPrice extends Model
{
    protected $fillable = [
        'repack_valuation_id', 'product_id', 'reference_group_id', 'gross_price',
        'trading_terms_percent', 'net_price', 'flag',
    ];

    protected $casts = [
        'gross_price' => 'float',
        'trading_terms_percent' => 'float',
        'net_price' => 'float',
    ];

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(RepackValuation::class, 'repack_valuation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function referenceGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'reference_group_id');
    }
}
