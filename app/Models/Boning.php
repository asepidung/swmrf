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

class Boning extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'bonings';

    protected $fillable = ['doc_no', 'boning_date', 'status', 'kunci', 'note', 'created_by'];

    protected $casts = [
        'boning_date' => 'date',
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
                $prefix = 'BN' . date('y');
                $count = static::withTrashed()->whereYear('created_at', $currentYear)->count();
                $sequence = $count + 1;
                $model->doc_no = $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
            }

            if (empty($model->status)) {
                $model->status = 'OPEN';
            }
        });
    }

    public function carcasses(): HasMany
    {
        return $this->hasMany(BoningCarcass::class, 'boning_id');
    }

    public function materialUsages(): MorphMany
    {
        return $this->morphMany(MaterialUsage::class, 'usageable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoningItem::class, 'boning_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
