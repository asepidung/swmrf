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
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CattleWeighing extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'weighing_number',
        'cattle_receiving_id',
        'weighing_date',
        'note',
        'created_by'
    ];

    protected $appends = [
        'receiving_number',
        'po_number',
        'supplier_name',
    ];

    protected $casts = [
        'weighing_date' => 'date',
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

            if (empty($model->weighing_number)) {
                DB::transaction(function () use ($model) {
                    $year = date('y');
                    $prefix = "CW#{$year}";
                    
                    $lastRecord = static::withTrashed()
                        ->where('weighing_number', 'like', "{$prefix}%")
                        ->lockForUpdate()
                        ->orderBy('weighing_number', 'desc')
                        ->first();

                    $sequence = 1;
                    if ($lastRecord) {
                        $lastSequence = (int) substr($lastRecord->weighing_number, -3);
                        $sequence = $lastSequence + 1;
                    }

                    $model->weighing_number = $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
                });
            }
        });

        static::deleting(function ($model) {
            if (\App\Models\Carcass::where('cattle_weighing_id', $model->id)->exists()) {
                throw new \Exception(__('Cannot delete Cattle Weighing because it has already been processed into Carcass.'));
            }
        });

        static::deleted(function ($model) {
            if ($model->isForceDeleting()) {
                $model->financialLoss()->forceDelete();
            } else {
                $model->financialLoss()->delete();
            }
        });

        static::restored(function ($model) {
            if ($model->financialLoss()->withTrashed()->exists()) {
                $model->financialLoss()->withTrashed()->restore();
            }
        });
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(CattleReceiving::class, 'cattle_receiving_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CattleWeighingItem::class, 'cattle_weighing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financialLoss(): MorphOne
    {
        return $this->morphOne(FinancialLoss::class, 'lossable');
    }

    public function calculateAndSaveFinancialLoss(): void
    {
        $this->refresh();
        $receiving = CattleReceiving::with('purchaseCattle.items')->find($this->cattle_receiving_id);
        
        $totalLoss = 0;
        
        if ($receiving && $receiving->purchaseCattle) {
            $po = $receiving->purchaseCattle;
            $poItems = $po->items->keyBy('cattle_class_id');
            $supplierId = $po->supplier_id;
            
            foreach ($this->items as $itemData) {
                $initial = floatval($itemData->initial_weight ?? 0);
                $actual = floatval($itemData->actual_weight ?? 0);
                
                if ($actual < $initial) {
                    $lossWeight = $initial - $actual;
                    $classId = $itemData->cattle_class_id ?? null;
                    $price = 0;
                    
                    if ($classId) {
                        // 1. Cek di PO saat ini
                        if (isset($poItems[$classId])) {
                            $price = $poItems[$classId]->price;
                        } else {
                            // 2. Cek histori harga terakhir dari supplier yg sama untuk class ini
                            $lastPurchaseItem = \App\Models\PurchaseCattleItem::where('cattle_class_id', $classId)
                                ->whereHas('purchaseCattle', function ($q) use ($supplierId) {
                                    $q->where('supplier_id', $supplierId);
                                })
                                ->latest('created_at')
                                ->first();

                            if ($lastPurchaseItem && $lastPurchaseItem->price > 0) {
                                $price = $lastPurchaseItem->price;
                            } else {
                                // 3. Fallback: Rata-rata harga class yg ada di PO ini
                                if ($poItems->count() > 0) {
                                    $price = $poItems->avg('price');
                                }
                            }
                        }
                    }
                    
                    $totalLoss += ($lossWeight * $price);
                }
            }
        }
        
        if ($totalLoss > 0) {
            $this->financialLoss()->updateOrCreate(
                ['transaction_type' => 'Cattle Weighing', 'reference_number' => $this->weighing_number],
                ['date' => $this->weighing_date, 'amount' => $totalLoss, 'note' => 'Susut Timbang Ulang Sapi']
            );
        } else {
            $this->financialLoss()->delete();
        }
    }

    public function getReceivingNumberAttribute()
    {
        return $this->receiving->receiving_number ?? null;
    }

    public function getPoNumberAttribute()
    {
        return optional($this->receiving->purchaseCattle)->document_number;
    }

    public function getSupplierNameAttribute()
    {
        return optional($this->receiving->supplier)->name;
    }
}
