<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\WarehouseFreezeService;

class BeefStock extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted()
    {
        static::creating(function ($model) {
            WarehouseFreezeService::check($model->warehouse_id);
        });

        static::updating(function ($model) {
            WarehouseFreezeService::check($model->warehouse_id);
            // If they change warehouse, check the old one too
            if ($model->isDirty('warehouse_id')) {
                WarehouseFreezeService::check($model->getOriginal('warehouse_id'));
            }
        });

        static::deleting(function ($model) {
            WarehouseFreezeService::check($model->warehouse_id);
        });
    }

    protected $table = 'beef_stocks';

    protected $fillable = [
        'barcode',
        'product_id',
        'warehouse_id',
        'grade_id',
        'weight',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
        'origin',
        'status',
        'note'
    ];

    protected $casts = [
        'weight' => 'float',
        'qty_pcs' => 'integer',
        'ph_level' => 'float',
        'pack_date' => 'date',
        'exp_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }
}
