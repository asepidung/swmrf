<?php

namespace App\Models;

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
                $datePrefix = date('ymd');
                $latest = self::withTrashed()
                    ->where('return_number', 'LIKE', "RTRN-{$datePrefix}-%")
                    ->orderBy('id', 'desc')
                    ->first();

                $nextId = $latest ? (int) substr($latest->return_number, -4) + 1 : 1;
                $model->return_number = "RTRN-{$datePrefix}-" . str_pad($nextId, 4, '0', STR_PAD_LEFT);
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
