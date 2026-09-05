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
    public const STATUS_SEDANG_MENGHITUNG = ['DRAFT', 'IN_PROGRESS', 'REVIEW'];

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
