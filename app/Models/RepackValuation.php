<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Snapshot valuasi sebuah repack yang dikunci (HPP tahap 2, issue #522).
 * Ditulis oleh `App\Services\RepackValuator`; hanya dibaca di tempat lain.
 */
class RepackValuation extends Model
{
    use LogsActivity;

    public const STATUS_VALUED = 'valued';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'repack_id', 'total_input_kg', 'total_input_cost', 'shrink_kg', 'shrink_value',
        'ratio_k', 'status', 'needs_review', 'valued_at',
    ];

    protected $casts = [
        'total_input_kg' => 'float',
        'total_input_cost' => 'float',
        'shrink_kg' => 'float',
        'shrink_value' => 'float',
        'ratio_k' => 'float',
        'needs_review' => 'boolean',
        'valued_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('repack_valuations')
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function repack(): BelongsTo
    {
        return $this->belongsTo(Repack::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RepackValuationPrice::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
