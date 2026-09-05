<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;

class MaterialStockTake extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'document_number',
        'date',
        'period',
        'status',
        'summary_note',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * Status yang berarti "hitungannya sedang berlangsung".
     *
     * REVIEW ikut masuk, dan itu memang berbeda dari opname daging: hanya
     * opname material yang punya tahap REVIEW. Perbedaannya bukan
     * ketidakkonsistenan, melainkan dua kosakata status yang memang berbeda.
     */
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    /** Tahap yang TIDAK dimiliki opname daging. */
    public const STATUS_REVIEW = 'REVIEW';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELED = 'CANCELED';

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => self::STATUS_DRAFT,
            self::STATUS_IN_PROGRESS => self::STATUS_IN_PROGRESS,
            self::STATUS_REVIEW => self::STATUS_REVIEW,
            self::STATUS_COMPLETED => self::STATUS_COMPLETED,
            self::STATUS_CANCELED => self::STATUS_CANCELED,
        ];
    }

    public const STATUS_SEDANG_MENGHITUNG = [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS, self::STATUS_REVIEW];

    /** Hitungannya masih boleh diisi dan diubah? */
    public function isCountable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }

    /**
     * Boleh dihapus?
     *
     * Hanya selama belum ada satu pun hitungan yang diisi. Menghapus opname
     * yang sudah dihitung membuang pekerjaan orang gudang, dan -- sejak stok
     * material ikut dibekukan -- penghapusannya juga MENCAIRKAN pembekuan
     * tanpa memberi tahu siapa pun, karena baris terhapus lunak tidak lagi
     * terlihat oleh penjaganya.
     */
    public function isDeletable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true)
            && $this->items()->whereNotNull('physical_qty')->doesntExist();
    }

    /**
     * Menerapkan hasil hitungan ke stok. SATU jalur, dipakai semua tombol.
     *
     * Sebelum ini ada DUA tombol "selesaikan opname" dengan arti yang berbeda:
     *
     *   ManageMaterialStockTakeItems  -> StockService::adjustStock(selisih)
     *                                    menambahkan SELISIH, lewat service
     *                                    yang mengunci baris
     *   EditMaterialStockTake         -> $stock->qty = physical_qty
     *                                    MENIMPA, menulis stok dan buku besar
     *                                    dengan tangan, tanpa penguncian
     *
     * Dua arti untuk satu tindakan. Kalau stok bergerak sejak hitungan
     * dimulai, keduanya menghasilkan angka akhir yang berlainan -- dan hanya
     * yang kedua yang mencatat siapa yang menyelesaikannya.
     *
     * Sekarang satu jalur, lewat `StockService` supaya penguncian dan
     * pencatatan buku besarnya sama dengan seluruh pergerakan material lain.
     * Pembekuan dilewati HANYA selama penerapan ini, dan dipulihkan lewat
     * `finally`.
     */
    public function applyToStock(): void
    {
        \App\Services\MaterialStockFreezeService::bypass(function (): void {
            \Illuminate\Support\Facades\DB::transaction(function (): void {
                foreach ($this->items()->whereNotNull('physical_qty')->get() as $item) {
                    if ((float) $item->difference_qty === 0.0) {
                        continue;
                    }

                    \App\Services\StockService::adjustStock(
                        $item->material_id,
                        (float) $item->difference_qty,
                        'STOCK_TAKE_ADJUSTMENT',
                        $this->document_number,
                        'Stock Take Adjustment '.$this->document_number,
                    );
                }

                $this->update([
                    'status' => self::STATUS_COMPLETED,
                    'completed_by' => auth()->id(),
                    'completed_at' => now(),
                ]);
            });
        });
    }

    /**
     * Apakah ada opname material yang sedang berlangsung?
     *
     * Selama berlangsung, angka stok di layar disamarkan menjadi `***`.
     * Gunanya supaya orang yang menghitung tidak bisa membaca jawabannya dari
     * sistem lebih dulu -- hitungan yang menyalin angka sistem tidak
     * menemukan apa pun.
     *
     * Pertanyaan ini dulu ditulis ULANG EMPAT KALI di `MaterialStockResource`
     * -- di form, dan di tiga penutup kolom. Empat salinan aturan yang sama
     * berarti empat tempat yang harus ingat, dan yang lupa tidak akan pernah
     * terlihat sebagai error: angkanya hanya muncul, di tempat yang seharusnya
     * tidak.
     *
     * Dan memang ada yang lupa: kedua tombol ekspor mencetak angka aslinya.
     */
    public static function isCounting(): bool
    {
        return static::whereIn('status', static::STATUS_SEDANG_MENGHITUNG)->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->document_number)) {
                $currentYear = date('Y');
                $model->document_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'document_number',
                    prefix: 'MST#'.date('y'),
                    padding: 3,
                );
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialStockTakeItem::class, 'material_stock_take_id');
    }
}
