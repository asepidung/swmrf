<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialUnit extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // Kembar dengan MaterialCategory::booted() -- lihat penjelasan di sana.
        static::deleting(function (self $unit) {
            if ($unit->materials()->exists()) {
                throw new \Exception(__('This material unit cannot be deleted because it is still used by existing materials.'));
            }
        });
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'material_unit_id');
    }
}
