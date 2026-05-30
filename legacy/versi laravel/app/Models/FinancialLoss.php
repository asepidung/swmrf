<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialLoss extends Model
{
    protected $fillable = [
        'lossable_type',
        'lossable_id',
        'reference_number',
        'loss_date',
        'total_amount',
        'status',
        'note',
        'created_by'
    ];

    // Relasi Polymorphic (Nanti bisa nyambung ke WeighingLoss, RepackLoss, dll)
    public function lossable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
