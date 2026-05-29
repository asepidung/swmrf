<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseCattle extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'purchase_cattles';

    protected $fillable = [
        'document_number',
        'supplier_id',
        'shipping_date',
        'summary_note',
        'created_by',
    ];

    protected $casts = [
        'shipping_date' => 'date',
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

            // Generate document number if not provided
            if (empty($model->document_number)) {
                DB::transaction(function () use ($model) {
                    $year = date('y');
                    $prefix = "SWM-CPO#{$year}";
                    
                    $lastRecord = static::withTrashed()
                        ->where('document_number', 'like', "{$prefix}%")
                        ->lockForUpdate()
                        ->orderBy('document_number', 'desc')
                        ->first();

                    $sequence = 1;
                    if ($lastRecord) {
                        $lastSequence = (int) substr($lastRecord->document_number, -3);
                        $sequence = $lastSequence + 1;
                    }

                    $model->document_number = $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
                });
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseCattleItem::class, 'purchase_cattle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
