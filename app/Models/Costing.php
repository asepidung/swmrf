<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Costing per lot boning (Mesin HPP, issue #480) -- metode Relative Sales
 * Value, `.agents/hpp.md` §2/§6. Satu costing per `Boning` (unik).
 *
 * Draft -> Locked. Draft boleh "Hitung ulang" (mengganti overhead lalu
 * menjalankan `CostingCalculator` lagi); Locked adalah snapshot final,
 * tidak bisa diubah lagi.
 */
class Costing extends Model
{
    use SoftDeletes, LogsActivity;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_LOCKED = 'Locked';

    protected $fillable = [
        'costing_number',
        'costing_date',
        'boning_id',
        'purchase_cost',
        'total_sales_value',
        'ratio_k',
        'overhead_per_kg',
        'total_kg',
        'profit',
        'status',
        'note',
        'created_by',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'costing_date' => 'date',
        'purchase_cost' => 'float',
        'total_sales_value' => 'float',
        'ratio_k' => 'float',
        'overhead_per_kg' => 'float',
        'total_kg' => 'float',
        'profit' => 'float',
        'locked_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function boning(): BelongsTo
    {
        return $this->belongsTo(Boning::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CostingItem::class);
    }

    public function cattle(): HasMany
    {
        return $this->hasMany(CostingCattle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /** Margin% -- SAMA untuk seluruh produk, sifat metode ini (`hpp.md` §11.1), bukan per produk. */
    public function marginPercent(): float
    {
        return round((1 - $this->ratio_k) * 100, 2);
    }

    /** Overhead default costing baru: dari costing terakhir, atau 0 kalau belum ada satu pun. */
    public static function defaultOverheadPerKg(): float
    {
        return (float) (static::query()->latest('id')->value('overhead_per_kg') ?? 0);
    }

    /**
     * @throws \RuntimeException
     */
    public function lock(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException(__('Only a draft costing can be locked.'));
        }

        if ($this->items()->where('flag', CostingItem::FLAG_NO_PRICE)->exists()) {
            throw new \RuntimeException(__('This costing has a product without a price and cannot be locked.'));
        }

        $this->forceFill([
            'status' => self::STATUS_LOCKED,
            'locked_by' => Auth::id(),
            'locked_at' => now(),
        ])->save();
    }

    /**
     * @throws \RuntimeException
     */
    public function unlock(): void
    {
        if ($this->status !== self::STATUS_LOCKED) {
            throw new \RuntimeException(__('Only a locked costing can be unlocked.'));
        }

        $this->forceFill([
            'status' => self::STATUS_DRAFT,
            'locked_by' => null,
            'locked_at' => null,
        ])->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $costing) {
            if (empty($costing->costing_number)) {
                $costing->costing_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'costing_number',
                    prefix: 'HPP#'.date('y'),
                    padding: 4,
                );
            }
            if (empty($costing->created_by)) {
                $costing->created_by = Auth::id();
            }
            if (empty($costing->status)) {
                $costing->status = self::STATUS_DRAFT;
            }
        });

        static::updating(function (self $costing) {
            if ($costing->getOriginal('status') === self::STATUS_LOCKED) {
                $changed = array_diff(
                    array_keys($costing->getDirty()),
                    ['status', 'locked_by', 'locked_at', 'updated_at'],
                );

                if ($changed !== []) {
                    throw new \Exception(__('This costing is locked and can no longer be changed.'));
                }
            }
        });

        static::deleting(function (self $costing) {
            if ($costing->status !== self::STATUS_DRAFT) {
                throw new \Exception(__('Only a draft costing can be deleted.'));
            }
        });
    }
}
