<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DeliveryOrderReceipt extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'delivery_order_receipts';

    protected $fillable = [
        'delivery_order_id',
        'sales_order_id',
        'customer_id',
        'receipt_number',
        'delivery_date',
        'po_number',
        'note',
        'total_box',
        'total_weight',
        'status',
        'created_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'total_box' => 'integer',
        'total_weight' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }


    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderReceiptItem::class, 'delivery_order_receipt_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class, 'delivery_order_receipt_id');
    }

    protected static function booted()
    {
        static::creating(function (DeliveryOrderReceipt $receipt) {
            if (empty($receipt->created_by)) {
                $receipt->created_by = Auth::id();
            }
        });
    }
}
