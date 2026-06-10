<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepackResult extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'repack_results';

    protected $fillable = [
        'repack_id',
        'product_id',
        'warehouse_id',
        'grade_id',
        'barcode',
        'weight',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
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

    public function repack(): BelongsTo
    {
        return $this->belongsTo(Repack::class, 'repack_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
