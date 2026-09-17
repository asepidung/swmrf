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
     *
     * Mengembalikan `false` kalau tidak menerapkan apa pun (dokumennya
     * sudah diselesaikan lebih dulu, dari sesi lain atau klik ganda) --
     * pemanggilnya bertanggung jawab menunjukkan pesan yang sesuai, bukan
     * menganggap "sukses" begitu saja.
     */
    public function applyToStock(): bool
    {
        return \App\Services\MaterialStockFreezeService::bypass(function (): bool {
            return \Illuminate\Support\Facades\DB::transaction(function (): bool {
                // Baris dikunci dan status dibaca ULANG sebelum apa pun
                // diterapkan. Sebelumnya tidak ada penguncian di sini sama
                // sekali -- klik ganda (atau dua tab) bisa lolos
                // `isCountable()` di kedua sisi SEBELUM salah satunya
                // menulis status COMPLETED, lalu KEDUANYA menerapkan
                // selisih yang sama ke stok: setiap selisih diterapkan DUA
                // KALI, bukan sekali.
                $locked = self::whereKey($this->id)->lockForUpdate()->first();

                if (! $locked || ! $locked->isCountable()) {
                    return false;
                }

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

                $locked->update([
                    'status' => self::STATUS_COMPLETED,
                    'completed_by' => auth()->id(),
                    'completed_at' => now(),
                ]);
                $this->status = self::STATUS_COMPLETED;

                return true;
            });
        });
    }

    /**
     * Mengirim opname untuk ditinjau -- SEMUA baris ikut terkunci di sini,
     * termasuk yang sebelumnya dibuka lagi lewat "Minta Hitung Ulang".
     * Baris yang terkunci tidak bisa diedit sampai dibuka lagi lewat
     * `requestRecount()`.
     *
     * Mengembalikan `false` kalau dokumennya sudah tidak lagi bisa
     * dihitung (klik ganda / sesi lain sudah mengirimnya lebih dulu).
     */
    public function submitForReview(): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function (): bool {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isCountable()) {
                return false;
            }

            $locked->items()->update(['is_locked' => true]);

            $locked->update(['status' => self::STATUS_REVIEW]);
            $this->status = self::STATUS_REVIEW;

            return true;
        });
    }

    /**
     * Membuka kunci baris-baris TERTENTU untuk dihitung ulang, dan
     * mengembalikan dokumen ke IN_PROGRESS -- keputusan Owner, 17
     * September 2026: statusnya satu untuk seluruh dokumen (bukan
     * "sebagian REVIEW sebagian tidak"), tapi yang benar-benar terkunci
     * dari pengeditan adalah BARIS yang tidak diminta ulang, bukan status
     * dokumennya.
     *
     * Angka lama dicatat ke activity log SEBELUM dikosongkan -- riwayat
     * hitungan sebelumnya, bukan cuma "sekarang kosong" tanpa jejak.
     *
     * @param  array<int, int>  $itemIds
     * @return bool  `false` kalau dokumennya sudah bukan REVIEW lagi
     *               (klik ganda / sesi lain), atau kalau tidak ada satu
     *               pun item valid yang dipilih.
     */
    public function requestRecount(array $itemIds): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($itemIds): bool {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== self::STATUS_REVIEW) {
                return false;
            }

            $items = $locked->items()->whereIn('id', $itemIds)->lockForUpdate()->get();

            if ($items->isEmpty()) {
                return false;
            }

            foreach ($items as $item) {
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($item)
                    ->withProperties([
                        'previous_physical_qty' => $item->physical_qty,
                        'previous_difference_qty' => $item->difference_qty,
                    ])
                    ->log('Recount requested');

                $item->update([
                    'physical_qty' => null,
                    'difference_qty' => null,
                    'is_locked' => false,
                ]);
            }

            $locked->update(['status' => self::STATUS_IN_PROGRESS]);
            $this->status = self::STATUS_IN_PROGRESS;

            return true;
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
