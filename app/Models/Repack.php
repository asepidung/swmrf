<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Repack extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'repacks';

    protected $fillable = ['doc_no', 'repack_date', 'status', 'kunci', 'note', 'created_by'];

    protected $casts = [
        'repack_date' => 'date',
        'kunci' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->doc_no)) {
                $currentYear = date('Y');
                $prefix = 'RP#' . date('y');
                // Count records from that year based on repack_date
                $count = static::withTrashed()
                    ->whereYear('repack_date', $currentYear)
                    ->count();
                $sequence = $count + 1;
                $model->doc_no = $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
            }

            if (empty($model->status)) {
                $model->status = 'OPEN';
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(RepackMaterial::class, 'repack_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(RepackResult::class, 'repack_id');
    }

    public function materialUsages(): MorphMany
    {
        return $this->morphMany(MaterialUsage::class, 'usageable');
    }
}
