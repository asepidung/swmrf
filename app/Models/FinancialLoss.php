<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialLoss extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'lossable_type',
        'lossable_id',
        'date',
        'transaction_type',
        'reference_number',
        'amount',
        'note'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function lossable(): MorphTo
    {
        return $this->morphTo();
    }
}
