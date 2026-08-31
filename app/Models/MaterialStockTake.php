<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;

class MaterialStockTake extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'document_number',
        'date',
        'period',
        'status',
        'summary_note',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->document_number)) {
                $currentYear = date('Y');
                $model->document_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'document_number',
                    prefix: 'MST#'.date('y'),
                    padding: 3,
                );
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialStockTakeItem::class, 'material_stock_take_id');
    }
}
