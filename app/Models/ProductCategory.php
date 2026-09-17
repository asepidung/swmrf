<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = ['name', 'prefix'];

    protected static function booted(): void
    {
        // Kembar dengan MaterialCategory::booted() -- lihat penjelasan di sana.
        static::deleting(function (self $category) {
            if ($category->products()->exists()) {
                throw new \Exception(__('This product category cannot be deleted because it is still used by existing products.'));
            }
        });
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
