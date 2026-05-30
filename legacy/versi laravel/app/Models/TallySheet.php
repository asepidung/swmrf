<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallySheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'tally_number',
        'tally_date',
        'operator_id',
        'status',
        'note',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TallyItem::class, 'tally_sheet_id');
    }

    protected static function booted()
    {
        static::deleting(function ($tallySheet) {
            if ($tallySheet->salesOrder) {
                $tallySheet->salesOrder->update([
                    'status' => 'Cancelled'
                ]);
            }
        });
    }
}
