<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Tally extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'tallies';

    protected $fillable = [
        'sales_order_id',
        'tally_number',
        'status', // 'processing', 'locked'
        'seal_number',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TallyItem::class, 'tally_id');
    }

    public function deliveryOrder(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryOrder::class, 'tally_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        static::creating(function (Tally $tally) {
            if (empty($tally->created_by)) {
                if (Auth::check()) {
                    $tally->created_by = Auth::id();
                } else {
                    $tally->created_by = User::first()?->id ?? 1;
                }
            }

            if (empty($tally->tally_number)) {
                $year2Digit = date('y');
                $currentYearFull = date('Y');

                $lastTally = self::withTrashed()
                    ->whereYear('created_at', $currentYearFull)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextSequence = 1;

                if ($lastTally && !empty($lastTally->tally_number)) {
                    // tally_number format: TS#YYxxxx (e.g. TS#260001)
                    // The sequence is the last 4 characters
                    $lastSequence = (int) substr($lastTally->tally_number, -4);
                    $nextSequence = $lastSequence + 1;
                }

                $tally->tally_number = 'TS#' . $year2Digit . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            }
        });

        static::deleting(function (Tally $tally) {
            // Restore all items back to stock by deleting them
            $tally->items()->get()->each->delete();

            // Revert sales order status to 'waiting'
            if ($tally->salesOrder) {
                $tally->salesOrder->update(['status' => 'waiting']);
            }
        });
    }
}
