<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DeliveryOrder extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'tally_id',
        'sales_order_id',
        'customer_id',
        'delivery_order_number',
        'delivery_date',
        'po_number',
        'driver',
        'police_number',
        'seal_number',
        'note',
        'status',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tally(): BelongsTo
    {
        return $this->belongsTo(Tally::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receipt(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryOrderReceipt::class, 'delivery_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financialLoss(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(FinancialLoss::class, 'lossable');
    }

    public function syncItemsFromTally(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            // Delete old items
            $this->items()->delete();

            // Fetch current tally items and aggregate by product_id
            if ($this->tally) {
                $aggregated = [];
                foreach ($this->tally->items as $tallyItem) {
                    if (!isset($aggregated[$tallyItem->product_id])) {
                        $aggregated[$tallyItem->product_id] = [
                            'product_id' => $tallyItem->product_id,
                            'box' => 0,
                            'weight' => 0,
                        ];
                    }
                    $aggregated[$tallyItem->product_id]['box'] += 1;
                    $aggregated[$tallyItem->product_id]['weight'] += (float)$tallyItem->weight;
                }

                foreach ($aggregated as $data) {
                    $this->items()->create($data);
                }
            }
        });
    }

    protected static function booted()
    {
        static::creating(function (DeliveryOrder $do) {
            if (empty($do->created_by)) {
                $do->created_by = Auth::id() ?? User::first()?->id ?? 1;
            }

            if (empty($do->status)) {
                $do->status = 'Ready';
            }

            if (empty($do->delivery_order_number)) {
                $year2Digit = date('y');
                $currentYearFull = date('Y');

                $lastDo = self::withTrashed()
                    ->whereYear('created_at', $currentYearFull)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextSequence = 1;

                if ($lastDo && !empty($lastDo->delivery_order_number)) {
                    // delivery_order_number format: SWM-DO#YYxxxx (e.g. SWM-DO#260001)
                    $lastSequence = (int) substr($lastDo->delivery_order_number, -4);
                    $nextSequence = $lastSequence + 1;
                }

                $do->delivery_order_number = 'SWM-DO#' . $year2Digit . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            }
        });

        static::created(function (DeliveryOrder $do) {
            if ($do->tally) {
                $do->tally->update(['status' => 'do']);
            }
            if ($do->salesOrder) {
                $do->salesOrder->update(['status' => 'on_delivery']);
            }
        });

        static::deleted(function (DeliveryOrder $do) {
            if ($do->isForceDeleting()) {
                $do->financialLoss()->forceDelete();
            } else {
                $do->financialLoss()->delete();
            }

            if ($do->tally) {
                $do->tally->update(['status' => 'locked']);
            }
            if ($do->salesOrder) {
                $do->salesOrder->update(['status' => 'processing']);
            }
        });

        static::restored(function (DeliveryOrder $do) {
            if ($do->financialLoss()->withTrashed()->exists()) {
                $do->financialLoss()->withTrashed()->restore();
            }
        });
    }
}
