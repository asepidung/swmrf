<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'created_by',
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
                $currentYearFull = date('Y');

                $lastOrder = self::withTrashed()
                    ->whereYear('created_at', $currentYearFull)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextSequence = 1;

                if ($lastOrder && !empty($lastOrder->so_number)) {
                    // so_number format: SO#YYxxxx (e.g. SO#260001)
                    // The sequence is the last 4 characters
                    $lastSequence = (int) substr($lastOrder->so_number, -4);
                    $nextSequence = $lastSequence + 1;
                }

                $model->so_number = 'SO#' . $year2Digit . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
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
}
