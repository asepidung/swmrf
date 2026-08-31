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

class CattleReceiving extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'cattle_receivings';

    protected $fillable = [
        'receiving_number',
        'purchase_cattle_id',
        'supplier_id',
        'receive_date',
        'doc_no',
        'sv_ok',
        'skkh_ok',
        'note',
        'created_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
        'sv_ok' => 'boolean',
        'skkh_ok' => 'boolean',
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

            // Generate GRC/CR number if not provided
            if (empty($model->receiving_number)) {
                $model->receiving_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'receiving_number',
                    prefix: 'CR#'.date('y'),
                    padding: 3,
                );
            }
        });
    }

    public function purchaseCattle(): BelongsTo
    {
        return $this->belongsTo(PurchaseCattle::class, 'purchase_cattle_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CattleReceivingItem::class, 'cattle_receiving_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function weighing(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CattleWeighing::class, 'cattle_receiving_id');
    }
}
