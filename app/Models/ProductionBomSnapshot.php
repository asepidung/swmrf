<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pemakaian bahan menurut BOM yang DIBEKUKAN saat sebuah Boning/Repack dikunci.
 * Satu baris per material. Lihat `HasProductionMaterialRecord`.
 */
class ProductionBomSnapshot extends Model
{
    protected $fillable = ['snapshotable_type', 'snapshotable_id', 'material_id', 'qty', 'unit_price', 'amount'];

    protected $casts = ['qty' => 'integer', 'unit_price' => 'float', 'amount' => 'float'];

    public function snapshotable(): MorphTo
    {
        return $this->morphTo();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
