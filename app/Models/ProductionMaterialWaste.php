<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bahan yang terbuang saat sebuah Boning/Repack. Tidak memotong stok.
 */
class ProductionMaterialWaste extends Model
{
    protected $fillable = ['wasteable_type', 'wasteable_id', 'material_id', 'qty', 'reason'];

    protected $casts = ['qty' => 'integer'];

    public function wasteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
