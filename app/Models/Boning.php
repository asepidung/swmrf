<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Boning extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'bonings';

    protected $fillable = [
        'doc_no', 'boning_date', 'status', 'kunci', 'note', 'created_by',
        'yield_override_reason', 'yield_override_by', 'yield_override_at',
    ];

    protected $casts = [
        'yield_override_at' => 'datetime',
        'boning_date' => 'date',
        'kunci' => 'boolean',
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

            if (empty($model->doc_no)) {
                /*
                 * Dulu nomornya dihitung dari COUNT baris, bukan dari nomor
                 * terakhir -- satu-satunya generator di aplikasi ini yang
                 * begitu.
                 *
                 * Akibatnya nomor bisa TERULANG: satu dokumen yang dihapus
                 * permanen membuat hitungan turun, dan dokumen berikutnya
                 * memakai nomor yang sudah dipakai, langsung menabrak unique
                 * index dengan error yang tidak menjelaskan apa-apa. Tidak ada
                 * pula penguncian, sehingga dua penyimpanan bersamaan
                 * mendapat hitungan yang sama.
                 */
                $model->doc_no = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'doc_no',
                    prefix: 'BN'.date('y'),
                    padding: 3,
                );
            }

            if (empty($model->status)) {
                $model->status = 'OPEN';
            }
        });
    }

    public function carcasses(): HasMany
    {
        return $this->hasMany(BoningCarcass::class, 'boning_id');
    }

    public function materialUsages(): MorphMany
    {
        return $this->morphMany(MaterialUsage::class, 'usageable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoningItem::class, 'boning_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function yieldOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yield_override_by');
    }

    // =================================================================
    // Hasil: apa yang masuk dibanding apa yang keluar
    // =================================================================

    /**
     * Berat karkas yang masuk ke batch ini.
     *
     * Angkanya SUDAH ADA sejak lama -- di `carcass_items`, per ekor, sebagai
     * belahan A, belahan B, kulit, dan buntut. Yang tidak ada hanyalah satu
     * baris kode yang membacanya. Sampai 4 September 2026 kata `weight` cuma
     * muncul SEKALI di seluruh `BoningResource`, dan itu pun `->weight('bold')`
     * -- ketebalan huruf.
     *
     * Kulit tidak ikut: ia dijual langsung ke kontraktor.
     *
     * Jeroan pun tidak ikut -- dan beratnya memang TIDAK PERNAH DITIMBANG.
     * Lihat catatan di `Carcass::boningInputWeight()`.
     */
    public function inputWeight(): float
    {
        return round(
            $this->carcasses
                ->map(fn (BoningCarcass $baris): float => $baris->carcass?->boningInputWeight() ?? 0.0)
                ->sum(),
            2,
        );
    }

    /** Berat seluruh potongan yang keluar dari batch ini. */
    public function outputWeight(): float
    {
        return round((float) $this->items()->sum('weight'), 2);
    }

    public function shrinkWeight(): float
    {
        return round($this->inputWeight() - $this->outputWeight(), 2);
    }

    /**
     * Susutnya dalam persen, atau `null` kalau belum ada karkas sama sekali.
     *
     * Pada data sungguhan 28 Agustus 2026: bahan 3.968,22 kg, hasil 3.938,38
     * kg -- susut 29,84 kg atau 0,75%. Itu angka nyata pertama yang dimiliki
     * proyek ini untuk susut boning, dan berguna saat QC menentukan ambangnya.
     */
    public function shrinkPercent(): ?float
    {
        $masuk = $this->inputWeight();

        if ($masuk <= 0) {
            return null;
        }

        return round(($this->shrinkWeight() / $masuk) * 100, 2);
    }

    /** Ambang susut wajar boning, atau `null` kalau BELUM disetel. */
    public static function shrinkLimitPercent(): ?float
    {
        return Setting::number(Setting::BONING_MAX_SHRINK_PERCENT);
    }

    /**
     * Susut batch ini masih di dalam batas wajar?
     *
     * TRUE juga ketika ambangnya belum disetel -- gerbangnya memang belum
     * menyala. Hasil yang LEBIH BERAT daripada karkasnya selalu di luar batas,
     * berapa pun ambangnya: itu mustahil secara fisik.
     */
    public function isWithinShrinkLimit(): bool
    {
        $persen = $this->shrinkPercent();

        if ($persen === null) {
            return true;
        }

        if ($persen < 0) {
            return false;
        }

        $ambang = static::shrinkLimitPercent();

        return $ambang === null || $persen <= $ambang;
    }

    public function shrinkLimitWasOverridden(): bool
    {
        return $this->yield_override_at !== null;
    }

    /**
     * Kunci batch ini.
     *
     * Bentuknya sama persis dengan `Repack::lock()`, dan itu disengaja: dua
     * proses yang menjawab pertanyaan yang sama sebaiknya dijawab dengan cara
     * yang sama, supaya yang membaca salah satunya sudah mengerti yang lain.
     *
     * Izin TIDAK diperiksa di sini. Model memegang aturannya, halaman
     * memegang kewenangannya.
     *
     * @throws \RuntimeException
     */
    public function lock(?string $overrideReason = null, ?int $userId = null): void
    {
        if ($this->kunci) {
            throw new \RuntimeException(__('This boning is already locked.'));
        }

        // TIDAK ADA syarat harus punya karkas.
        //
        // Sempat ada, dan itu keliru: kulit dan jeroan masuk stok lewat
        // dokumen boning TERSENDIRI yang memang tidak punya karkas -- Project
        // Owner menyebutnya "bikin boning baru, bikin label kulit dan offal".
        // Mensyaratkan karkas berarti dokumen semacam itu tidak akan pernah
        // bisa dikunci.
        //
        // Boning tanpa karkas memang tidak bisa dinilai susutnya, dan itu
        // sudah terbaca sendiri: `shrinkPercent()` mengembalikan `null`, dan
        // daftarnya menuliskan "tanpa karkas" alih-alih angka. Menahannya
        // bukan cara menyampaikan itu.

        if ($this->items()->doesntExist()) {
            throw new \RuntimeException(__('This boning has no output goods yet.'));
        }

        $menembus = ! $this->isWithinShrinkLimit();

        if ($menembus && ($overrideReason === null || trim($overrideReason) === '')) {
            throw new \RuntimeException(__('The shrinkage of this boning is outside the reasonable limit, so it needs an approval with a written reason.'));
        }

        $this->forceFill([
            'kunci' => true,
            'status' => 'LOCKED',
            'yield_override_reason' => $menembus ? trim((string) $overrideReason) : null,
            'yield_override_by' => $menembus ? ($userId ?? Auth::id()) : null,
            'yield_override_at' => $menembus ? now() : null,
        ])->save();
    }

    /** Buka kuncinya, beserta jejak penembusannya. */
    public function unlock(): void
    {
        if (! $this->kunci) {
            throw new \RuntimeException(__('This boning is not locked.'));
        }

        $this->forceFill([
            'kunci' => false,
            'status' => 'OPEN',
            'yield_override_reason' => null,
            'yield_override_by' => null,
            'yield_override_at' => null,
        ])->save();
    }
}
