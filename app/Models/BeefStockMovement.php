<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class BeefStockMovement extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'beef_stock_movements';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'condition',
        'barcode',
        'transaction_type',
        'reference_document',
        'weight_in',
        'weight_out',
        'pcs_in',
        'pcs_out',
        'note',
        'created_by'
    ];

    protected $casts = [
        'weight_in' => 'float',
        'weight_out' => 'float',
        'pcs_in' => 'integer',
        'pcs_out' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
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
        return $this->belongsTo(Grade::class, 'condition'); // condition field stores grade_id
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
