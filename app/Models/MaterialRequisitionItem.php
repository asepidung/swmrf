<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MaterialRequisitionItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'material_requisition_id',
        'material_id',
        'qty',
        'price',
        'subtotal',
        'note',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->subtotal = $model->qty * $model->price;
        });

        static::saved(function ($model) {
            $model->requisition?->updateTotalAmount();
        });

        static::deleted(function ($model) {
            $model->requisition?->updateTotalAmount();
        });
    }

    public function requisition()
    {
        return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id');
    }

    public function item() // Note: use 'item' to match Filament standard for relation or 'material'
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
    
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
