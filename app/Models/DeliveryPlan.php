<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DeliveryPlan extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'customer_id',
        'delivery_date',
        'driver',
        'armada',
        'load_time',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'customer.name',
                'delivery_date',
                'driver',
                'armada',
                'load_time',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getNotesAttribute(): string
    {
        return $this->salesOrders->pluck('delivery_note')->filter()->unique()->implode(' | ');
    }

    public function getSalesOrdersCountAttribute(): int
    {
        return $this->salesOrders()->count();
    }

    public function getTotalQtyAttribute(): float
    {
        return $this->salesOrders->sum(fn ($so) => $so->items()->sum('weight'));
    }
}
