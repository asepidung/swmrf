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
        'legacy_note',
        'costing_customer_group_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    /** Grup pelanggan yang harganya dipakai menilai produk ini saat costing (`hpp.md` §10) -- kosong = harga umum. */
    public function costingGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'costing_customer_group_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if (empty($product->costing_customer_group_id)) {
                return;
            }

            // `costing_customer_group_id()->first()`, bukan properti
            // `$product->costingGroup` -- relasi belongsTo yang sudah
            // diakses ikut tercache di instance ini dan tidak menyegarkan
            // diri walau baris grupnya berubah lewat objek lain (pola yang
            // sama dengan SalesReturnPlanItem::booted()).
            $group = $product->costingGroup()->first();

            if (! $group || ! $group->is_costing_reference) {
                throw new \Exception(__('This customer group is not marked as a costing reference, so it cannot be used here.'));
            }
        });
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
