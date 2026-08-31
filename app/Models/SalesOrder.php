<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'so_number',
        'customer_id',
        'delivery_date',
        'po_number',
        'shipping_address',
        'status',
        'note',
        'down_payment',
        'created_by',
        'delivery_plan_id',
        'delivery_note',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'down_payment' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'so_number', 
                'customer.name', 
                'delivery_date', 
                'status', 
                'po_number', 
                'shipping_address', 
                'note'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->so_number)) {
                $year2Digit = date('y');

                $model->so_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'so_number',
                    prefix: 'SO#'.$year2Digit,
                    padding: 4,
                );
            }
        });

        static::saving(function ($model) {
            if ($model->customer_id && $model->delivery_date) {
                // Normalize new date to Y-m-d string
                $normalizedDate = \Carbon\Carbon::parse($model->delivery_date)->format('Y-m-d');

                // Check if date or customer actually changed
                $dateChanged = $model->getOriginal('delivery_date')
                    ? \Carbon\Carbon::parse($model->getOriginal('delivery_date'))->format('Y-m-d') !== $normalizedDate
                    : true;
                
                $customerChanged = $model->isDirty('customer_id');

                if ($dateChanged || $customerChanged || is_null($model->delivery_plan_id)) {
                    $plan = DeliveryPlan::withTrashed()->firstOrCreate(
                        [
                            'customer_id' => $model->customer_id,
                            'delivery_date' => $normalizedDate
                        ],
                        [
                            'created_by' => auth()->id()
                        ]
                    );
                    if ($plan->trashed()) {
                        $plan->restore();
                    }
                    $model->delivery_plan_id = $plan->id;
                }

                // Keep the date stored in clean Y-m-d format
                $model->delivery_date = $normalizedDate;
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tally()
    {
        return $this->hasOne(Tally::class, 'sales_order_id');
    }

    public function deliveryPlan()
    {
        return $this->belongsTo(DeliveryPlan::class);
    }
}
