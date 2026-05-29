<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'material_category_id',
        'material_unit_id',
        'min_stock',
        'is_active',
        'show_in_stock'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_stock' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Material $material) {
            $latest = static::latest('id')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $material->code = 'MTR' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        });
    }

    public function category()
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function unit()
    {
        return $this->belongsTo(MaterialUnit::class, 'material_unit_id');
    }
}
