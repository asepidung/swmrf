<?php

namespace App\Models;

use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaterialUsage extends Model
{
    protected $fillable = [
        'usageable_type',
        'usageable_id',
        'material_id',
        'qty',
        'note',
    ];

    public function usageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    protected static function booted()
    {
        static::created(function ($model) {
            $docNo = $model->usageable->doc_no ?? ('#' . $model->usageable_id);
            $reference = class_basename($model->usageable_type) . ' ' . $docNo;
            StockService::adjustStock(
                $model->material_id,
                -abs($model->qty), // reduce stock
                'MATERIAL_USAGE',
                $reference,
                $model->note ?? 'Automatic deduction'
            );
        });

        static::updated(function ($model) {
            if ($model->isDirty('qty') || $model->isDirty('material_id')) {
                $docNo = $model->usageable->doc_no ?? ('#' . $model->usageable_id);
                $reference = class_basename($model->usageable_type) . ' ' . $docNo;
                
                // If material changed, revert old material stock and deduct new material
                if ($model->isDirty('material_id')) {
                    $oldMaterialId = $model->getOriginal('material_id');
                    $oldQty = $model->getOriginal('qty');
                    
                    // Revert old
                    StockService::adjustStock(
                        $oldMaterialId,
                        abs($oldQty),
                        'MATERIAL_USAGE_REVERT',
                        $reference,
                        'Revert due to material change'
                    );

                    // Deduct new
                    StockService::adjustStock(
                        $model->material_id,
                        -abs($model->qty),
                        'MATERIAL_USAGE',
                        $reference,
                        $model->note ?? 'Automatic deduction (updated)'
                    );
                } else {
                    // Only qty changed
                    $oldQty = $model->getOriginal('qty');
                    $newQty = $model->qty;
                    $diff = abs($oldQty) - abs($newQty); // positive diff = add back to stock, negative diff = deduct more
                    
                    if ($diff != 0) {
                        StockService::adjustStock(
                            $model->material_id,
                            $diff,
                            'MATERIAL_USAGE_ADJUST',
                            $reference,
                            'Qty updated'
                        );
                    }
                }
            }
        });

        static::deleted(function ($model) {
            $docNo = $model->usageable->doc_no ?? ('#' . $model->usageable_id);
            $reference = class_basename($model->usageable_type) . ' ' . $docNo;
            StockService::adjustStock(
                $model->material_id,
                abs($model->qty), // add back stock
                'MATERIAL_USAGE_REVERT',
                $reference,
                'Revert due to deletion'
            );
        });
    }
}
