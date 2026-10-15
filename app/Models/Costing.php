<?php

namespace App\Models;

use App\Filament\Admin\Resources\CostingResource;
use App\Services\CostingCalculator;
use App\Services\CostingPrices;
use App\Support\TaskNotifier;
use App\Support\BarcodeCosts;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        'overhead_total',
        'total_kg',
        'profit',
        'status',
        'note',
        'created_by',
        'locked_by',
        'locked_at',
        'saved_at',
    ];

    protected $casts = [
        'costing_date' => 'date',
        'purchase_cost' => 'float',
        'total_sales_value' => 'float',
        'ratio_k' => 'float',
        'overhead_per_kg' => 'float',
        'overhead_total' => 'float',
        'total_kg' => 'float',
        'profit' => 'float',
        'locked_at' => 'datetime',
        'saved_at' => 'datetime',
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

    /**
     * Buat costing baru dari hasil `CostingCalculator::calculate()` --
     * header, `costing_items`, dan `costing_cattle` dalam SATU transaksi.
     *
     * @param  array<string, mixed>  $result
     */
    public static function createFromCalculation(Boning $boning, array $result): self
    {
        return DB::transaction(function () use ($boning, $result): self {
            // Costing Draft yang dihapus hanya "dibuang" (hapus lunak) tetapi barisnya tetap memegang
            // `boning_id` yang UNIK -- membuat costing baru untuk boning yang sama lalu gagal dengan
            // "Duplicate entry". Yang terhapus selalu Draft (hanya Draft yang boleh dihapus), tanpa
            // nilai yang dipakai pihak lain, jadi dibuang sungguhan sebelum yang baru dibuat.
            static::onlyTrashed()->where('boning_id', $boning->id)->get()->each->forceDelete();

            $costing = static::create([
                'costing_date' => now()->toDateString(),
                'boning_id' => $boning->id,
                'purchase_cost' => $result['purchase_cost'],
                'total_sales_value' => $result['total_sales_value'],
                'ratio_k' => $result['ratio_k'],
                'overhead_per_kg' => $result['overhead_per_kg'],
                'overhead_total' => $result['overhead_total'],
                'total_kg' => $result['total_kg'],
                'profit' => $result['profit'],
            ]);

            $costing->applyCalculationRows($result);

            return $costing;
        });
    }

    /**
     * Terapkan ULANG hasil `CostingCalculator::calculate()` ke costing
     * yang SUDAH ADA -- dipakai "Hitung ulang" (Draft saja). Baris item
     * diperbarui di tempat, biaya sapi lama diganti yang baru -- keduanya murni
     * SNAPSHOT tanpa rujukan balik dari model lain.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws \RuntimeException
     */
    public function recalculate(array $result): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException(__('Only a draft costing can be recalculated.'));
        }

        DB::transaction(function () use ($result): void {
            $this->forceFill([
                'purchase_cost' => $result['purchase_cost'],
                'total_sales_value' => $result['total_sales_value'],
                'ratio_k' => $result['ratio_k'],
                'overhead_per_kg' => $result['overhead_per_kg'],
                'overhead_total' => $result['overhead_total'],
                'total_kg' => $result['total_kg'],
                'profit' => $result['profit'],
            ])->save();

            // Baris produk diperbarui di tempat (id-nya tetap, supaya sel yang sedang diketik di layar
            // tidak kehilangan barisnya); yang sudah tidak ada di boning dibuang.
            $this->items()->whereNotIn('product_id', collect($result['items'])->pluck('product_id'))->delete();
            foreach ($result['items'] as $row) {
                $this->items()->updateOrCreate(['product_id' => $row['product_id']], $row);
            }

            $this->cattle()->delete();
            foreach ($result['cattle'] as $row) {
                $this->cattle()->create($row);
            }
        });
    }

    /** @param array<string, mixed> $result */
    private function applyCalculationRows(array $result): void
    {
        foreach ($result['cattle'] as $row) {
            $this->cattle()->create($row);
        }

        foreach ($result['items'] as $row) {
            $this->items()->create($row);
        }
    }

    /**
     * Harga yang sedang tertera di Draft ini (product_id => gross+net), dipakai sebagai harga ketikan
     * saat dihitung ulang -- supaya yang diketik tidak tertimpa harga costing terakhir (#541).
     *
     * @return array<int, array{gross: float, net: float}>
     */
    public function currentPrices(): array
    {
        return $this->items()->where('net_price', '>', 0)->get()
            ->mapWithKeys(fn (CostingItem $i) => [$i->product_id => ['gross' => (float) $i->gross_price, 'net' => (float) $i->net_price]])
            ->all();
    }

    /** Buat costing (Draft) untuk sebuah boning terkunci: harga dan overhead dari costing terakhir. */
    public static function createFromBoning(Boning $boning): self
    {
        // Costing yang belum disimpan dari kunjungan sebelumnya dipakai lagi, bukan dibuat dobel.
        return static::where('boning_id', $boning->id)->first() ?? static::createFromCalculation($boning, CostingCalculator::forBoning($boning)->calculate());
    }

    /**
     * Boning baru saja dikunci: pemegang izin membuat costing diberi tahu, dengan tautan ke Draft
     * Costing. Tidak pernah melempar -- mengunci boning tidak boleh gagal karena pemberitahuan.
     */
    public static function notifyBoningLocked(Boning $boning): void
    {
        try {
            TaskNotifier::notifyPermissionHolders(
                ['create_costings'],
                __('Boning ready for costing'),
                __('Boning :doc is locked and ready for costing.', ['doc' => $boning->doc_no]),
                CostingResource::getUrl('draft'),
                'costing-boning-'.$boning->id,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Boning yang sudah ada hasilnya (terbuka atau terkunci) dan belum punya costing yang DISIMPAN. */
    public static function boningsWaiting(): \Illuminate\Database\Eloquent\Builder
    {
        return Boning::query()
            ->whereHas('items')
            ->whereDoesntHave('costing', fn ($q) => $q->whereNotNull('saved_at'));
    }

    /** Laba sebelum overhead (setara "Gross Profit" di Excel akunting): nilai jual net - biaya beli. */
    public function profitBeforeOverhead(): float
    {
        return round((float) $this->total_sales_value - (float) $this->purchase_cost, 2);
    }

    /** Total nilai jual pada harga GROSS (sebelum potongan); produk tanpa gross memakai net-nya. */
    public function grossSalesValue(): float
    {
        return (float) $this->items()->get()->sum(
            fn (CostingItem $item): float => (float) ($item->gross_price > 0 ? $item->gross_price : $item->net_price) * (float) $item->weight_kg
        );
    }

    /** Margin % terhadap nilai jual GROSS: (1 - (biaya beli + overhead) / nilai jual gross). */
    public function marginGrossPercent(): float
    {
        $gross = $this->grossSalesValue();

        return $gross > 0 ? round((1 - ((float) $this->purchase_cost + (float) $this->overhead_total) / $gross) * 100, 2) : 0.0;
    }

    /** Margin% (NET) -- SAMA untuk seluruh produk, sifat metode ini (`hpp.md` §11.1), bukan per produk. */
    public function marginPercent(): float
    {
        return round((1 - $this->ratio_k) * 100, 2);
    }

    /** Overhead per kg bila belum ada costing sama sekali (Owner, 14 Oktober 2026). */
    public const OVERHEAD_AWAL = 3000.0;

    /** Overhead default costing baru: dari costing terakhir, atau OVERHEAD_AWAL kalau belum ada satu pun. */
    public static function defaultOverheadPerKg(): float
    {
        return (float) (static::query()->latest('id')->value('overhead_per_kg') ?? self::OVERHEAD_AWAL);
    }

    /**
     * @throws \RuntimeException
     */
    public function lock(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException(__('Only a draft costing can be locked.'));
        }

        // Costing boleh disiapkan selagi boning masih terbuka, tetapi baru bisa dikunci setelah berat
        // finalnya pasti, yaitu setelah boning dikunci.
        if (! $this->boning?->kunci) {
            throw new \RuntimeException(__('Lock the boning first: the costing can only be locked after the boning is locked.'));
        }

        if ($this->items()->where('flag', CostingItem::FLAG_NO_PRICE)->exists()) {
            throw new \RuntimeException(__('This costing has a product without a price and cannot be locked.'));
        }

        DB::transaction(function (): void {
            $this->forceFill([
                'status' => self::STATUS_LOCKED,
                'locked_by' => Auth::id(),
                'locked_at' => now(),
                // Mengunci sekaligus menyimpan: costing terkunci selalu tampil di daftar.
                'saved_at' => $this->saved_at ?? now(),
            ])->save();

            // Barcode boning lot ini bernilai sekarang -- mundur, termasuk yang
            // sudah terjual atau jadi bahan repack (HPP tahap 2, issue #522).
            // Dikunci ulang sesudah unlock: nilai baru menimpa yang lama.
            BarcodeCosts::valueBoning($this);

            // Harga costing berikutnya mengikuti costing yang terkunci terakhir (#541).
            CostingPrices::rememberFrom($this);
        });
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
                    ['status', 'locked_by', 'locked_at', 'saved_at', 'updated_at'],
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
