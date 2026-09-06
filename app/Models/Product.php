<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category_id',
        'structure_type',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function beefStocks(): HasMany
    {
        return $this->hasMany(BeefStock::class, 'product_id');
    }

    /** Bahan penolong yang dipakai produk ini -- Bill of Material-nya. */
    public function billOfMaterials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class);
    }
}
