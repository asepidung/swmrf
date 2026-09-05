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
     * Status yang berarti "hitungannya sedang berlangsung".
     *
     * Perhatikan tidak ada REVIEW di sini, dan itu BUKAN kelalaian: hanya
     * opname material yang punya tahap REVIEW. Dua kosakata status yang
     * memang berbeda, bukan dua salinan aturan yang tidak sinkron.
     */
    public const STATUS_SEDANG_MENGHITUNG = ['DRAFT', 'IN_PROGRESS'];

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
