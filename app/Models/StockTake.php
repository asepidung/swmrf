<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTake extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'document_number',
        'period',
        'date',
        'status',
        'summary_note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Seluruh status yang sah untuk sebuah dokumen opname daging.
     *
     * Sebelumnya keempatnya ditulis sebagai teks mentah di belasan tempat.
     * Dua di antaranya -- DRAFT dan CANCELED -- ada di peta warna tetapi
     * TIDAK PERNAH ditulis oleh satu baris kode pun: `CreateStockTake`
     * langsung menyetel IN_PROGRESS, dan satu-satunya perubahan berikutnya
     * adalah COMPLETED.
     *
     * Keduanya tetap didaftarkan di sini, bukan dibuang. Basis data yang
     * berjalan tidak bisa diperiksa dari sini, dan membuang nilai yang
     * ternyata masih ada barisnya akan membuat dokumen lama kehilangan
     * warnanya tanpa gejala apa pun.
     */
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELED = 'CANCELED';

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => self::STATUS_DRAFT,
            self::STATUS_IN_PROGRESS => self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED => self::STATUS_COMPLETED,
            self::STATUS_CANCELED => self::STATUS_CANCELED,
        ];
    }

    /**
     * Status yang berarti "hitungannya sedang berlangsung".
     *
     * Perhatikan tidak ada REVIEW di sini, dan itu BUKAN kelalaian: hanya
     * opname material yang punya tahap REVIEW. Dua kosakata status yang
     * memang berbeda, bukan dua salinan aturan yang tidak sinkron.
     */
    public const STATUS_SEDANG_MENGHITUNG = [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS];

    /** Masih boleh dipindai dan diubah hitungannya? */
    public function isCountable(): bool
    {
        return in_array($this->status, self::STATUS_SEDANG_MENGHITUNG, true);
    }

    /**
     * Boleh dihapus?
     *
     * Hanya selama belum ada satu pun hasil hitungan di dalamnya. Menghapus
     * opname yang sudah dihitung berarti membuang pekerjaan orang gudang, dan
     * kalau ia sedang berjalan, penghapusannya juga MENCAIRKAN pembekuan
     * gudang tanpa memberi tahu siapa pun -- baris yang terhapus lunak tidak
     * lagi terlihat oleh `WarehouseFreezeService`.
     *
     * Penjagaan ini dulu hanya ada di halaman View. Halaman Edit dan aksi
     * hapus massal tidak menjaga apa pun.
     */
    public function isDeletable(): bool
    {
        return $this->isCountable()
            && $this->items()->whereIn('status', ['MATCHED', 'UNEXPECTED'])->doesntExist();
    }

    /**
     * Apakah ada opname daging yang sedang berlangsung?
     *
     * Selama berlangsung, angka yang bisa dipakai menyalin jawaban
     * disembunyikan: enam digit terakhir barcode di daftar stok, dan seluruh
     * angka di halaman Posisi Stok per Tanggal. Hitungan fisik yang bisa
     * menyalin jawabannya tidak memeriksa apa pun.
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class, 'stock_take_id');
    }
}
