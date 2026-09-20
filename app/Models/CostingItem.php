<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris produk di dalam sebuah `Costing` -- salinan harga yang
 * dipakainya, bukan rujukan ke `price_list_items` (`hpp.md` §6).
 */
class CostingItem extends Model
{
    /** Produk tanpa grup acuan -- dinilai harga umum, costing tetap jadi. */
    public const FLAG_NO_REFERENCE = 'NO_REFERENCE';

    /** Harga tidak ditemukan sama sekali -- nilai 0, menolak Lock. */
    public const FLAG_NO_PRICE = 'NO_PRICE';

    protected $fillable = [
        'costing_id',
        'product_id',
        'weight_kg',
        'reference_group_id',
        'gross_price',
        'trading_terms_percent',
        'net_price',
        'sales_value',
        'hpp_per_kg',
        'flag',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'gross_price' => 'float',
        'trading_terms_percent' => 'float',
        'net_price' => 'float',
        'sales_value' => 'float',
        'hpp_per_kg' => 'float',
    ];

    public function costing(): BelongsTo
    {
        return $this->belongsTo(Costing::class);
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
