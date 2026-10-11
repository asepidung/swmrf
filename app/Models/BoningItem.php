<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoningItem extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\InheritsBatch;

    protected $table = 'boning_items';

    protected $fillable = [
        'boning_id',
        'product_id',
        'warehouse_id',
        'grade_id',
        'weight',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
        'barcode',
        'batch_no',
        'created_by'
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

    protected static function booted(): void
    {
        // Barcode boning lahir belum bernilai; nilainya datang saat costing lot
        // ini dikunci (HPP tahap 2, issue #522).
        static::created(function (self $item): void {
            if (filled($item->barcode)) {
                \App\Support\BarcodeCosts::ensure($item->barcode, $item->product_id, \App\Models\BarcodeCost::SOURCE_BONING);
            }
        });
    }

    public function boning(): BelongsTo
    {
        return $this->belongsTo(Boning::class, 'boning_id');
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
