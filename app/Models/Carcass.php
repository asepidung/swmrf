<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carcass extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'carcasses';

    protected $fillable = [
        'carcass_number',
        'cattle_weighing_id',
        'kill_date',
        'note',
        'created_by'
    ];

    protected $casts = [
        'kill_date' => 'date',
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

            if (empty($model->carcass_number)) {
                $model->carcass_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'carcass_number',
                    prefix: 'CC#'.date('y'),
                    padding: 3,
                );
            }
        });

        // Cascading Delete
        static::deleting(function ($carcass) {
            if (\App\Models\BoningCarcass::where('carcass_id', $carcass->id)->exists()) {
                throw new \Exception(__('Cannot delete Carcass because it has already been processed into Boning.'));
            }

            if ($carcass->isForceDeleting()) {
                $carcass->items()->forceDelete();
            } else {
                $carcass->items()->delete();
            }
        });

        static::restoring(function ($carcass) {
            $carcass->items()->withTrashed()->restore();
        });
    }

    public function weighing(): BelongsTo
    {
        return $this->belongsTo(CattleWeighing::class, 'cattle_weighing_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarcassItem::class, 'carcass_id');
    }

    public function boningCarcasses(): HasMany
    {
        return $this->hasMany(BoningCarcass::class, 'carcass_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
