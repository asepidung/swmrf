<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;

class SalesReturn extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'return_number',
        'return_date',
        'delivery_order_id',
        'customer_id',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
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
            if (empty($model->return_number)) {
                $model->return_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'return_number',
                    prefix: 'SR#'.date('y'),
                    padding: 3,
                );
            }
            if (empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
