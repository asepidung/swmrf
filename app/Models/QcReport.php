<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Laporan QC yang mendampingi sebuah dokumen.
 *
 * Satu bentuk untuk semua titik: temuan dan catatan umum. Alasan lengkapnya
 * ada di migrasinya.
 */
class QcReport extends Model
{
    use SoftDeletes;

    /**
     * Dokumen yang boleh didampingi laporan QC.
     *
     * **Ini juga daftar titik QC-nya**, dan satu-satunya tempat daftar itu
     * ditulis. Menambah titik berarti menambah satu baris di sini, bukan
     * menambah satu modul.
     *
     * Kuncinya dipakai di URL. Jenis dokumennya TIDAK PERNAH diambil mentah
     * dari URL: alamat yang menyebut kelas apa pun akan membuat laporan QC
     * bisa ditempelkan ke model mana saja di aplikasi ini -- termasuk yang
     * tidak ada hubungannya dengan mutu.
     *
     * @var array<string, class-string<Model>>
     */
    public const DOKUMEN = [
        'carcass' => Carcass::class,
    ];

    protected $fillable = [
        'document_number',
        'reportable_type',
        'reportable_id',
        'occurred_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QcReport $laporan): void {
            if (! $laporan->document_number) {
                $laporan->document_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'document_number',
                    prefix: 'QC#'.date('y'),
                    padding: 4,
                );
            }

            if (! $laporan->created_by) {
                $laporan->created_by = auth()->id();
            }
        });
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function findings(): HasMany
    {
        return $this->hasMany(QcFinding::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Kunci URL untuk sebuah kelas dokumen, atau `null` kalau tidak didukung.
     *
     * Dipakai dua arah: membaca alamat, dan menyusunnya.
     */
    public static function kunciUntuk(string $kelas): ?string
    {
        return array_search($kelas, self::DOKUMEN, true) ?: null;
    }

    /** Kelas dokumen untuk sebuah kunci URL, atau `null` kalau tidak dikenal. */
    public static function kelasUntuk(?string $kunci): ?string
    {
        return $kunci === null ? null : (self::DOKUMEN[$kunci] ?? null);
    }

    /**
     * Nomor dokumen yang didampingi, apa pun jenisnya.
     *
     * Tiap dokumen menamai kolom nomornya sendiri-sendiri, dan tidak ada
     * antarmuka bersama yang menyatukannya. Daripada memaksakan satu, yang
     * dicari di sini kolom pertama yang ada -- dan kalau tidak satu pun
     * ketemu, yang ditampilkan id-nya, bukan tanda strip yang menyembunyikan
     * bahwa dokumennya memang ada.
     */
    public function nomorDokumen(): string
    {
        $dokumen = $this->reportable;

        if (! $dokumen) {
            return '-';
        }

        foreach (['document_number', 'carcass_number', 'number'] as $kolom) {
            if (filled($dokumen->{$kolom} ?? null)) {
                return (string) $dokumen->{$kolom};
            }
        }

        return '#'.$dokumen->getKey();
    }

    /** Nama jenis dokumennya, untuk dibaca manusia. */
    public function jenisDokumen(): string
    {
        return __(class_basename($this->reportable_type));
    }
}
