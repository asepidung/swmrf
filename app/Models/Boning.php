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
    ];

    protected $casts = [
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

    /** Laporan QC yang mendampingi dokumen ini. */
    public function qcReports(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\QcReport::class, 'reportable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoningItem::class, 'boning_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =================================================================
    // Mengapa TIDAK ADA hitungan susut di sini
    // =================================================================
    //
    // Keputusan Project Owner, 5 September 2026, dan alasannya ada pada bentuk
    // pekerjaannya sendiri.
    //
    // Kulit dan offal diberi label DI DALAM dokumen boning yang sama. Itu
    // satu-satunya pintu agar keduanya punya stok: kontraktor mengambilnya
    // hari itu juga, dan untuk membawa barang dibutuhkan DO, yang butuh Sales
    // Order, yang butuh Tally, yang butuh STOK.
    //
    // Akibatnya hasil sebuah boning memuat barang yang tidak berasal dari
    // karkasnya:
    //
    //     bahan = karkas + buntut
    //     hasil = daging + offal + kulit
    //
    // sehingga tiap batch akan terbaca hasilnya jauh melebihi bahannya. Alarm
    // palsu pada SETIAP dokumen, yang justru mengajari orang mengabaikan
    // alarm.
    //
    // Bisa diperbaiki dengan menandai produk mana yang by-product karkas,
    // tetapi itu menambah satu daftar yang harus dirawat manusia demi angka
    // yang memang tidak dibutuhkan. Jangan menghidupkannya kembali tanpa
    // membaca alur satu batch boning di `agents.md` lebih dulu.
    //
    // Yang TETAP ADA dan berbeda urusan: `Carcass::yieldPercent()` -- berapa
    // persen bobot hidup yang menjadi karkas. Ia tidak menyentuh hasil boning
    // sama sekali.

    /**
     * Kunci batch ini.
     *
     * SATU RUMAH untuk seluruh syaratnya. Sebelumnya penguncian hanya menyetel
     * penanda langsung dari halaman, tanpa satu pun pemeriksaan.
     *
     * Izin TIDAK diperiksa di sini. Model memegang aturannya, halaman
     * memegang kewenangannya.
     *
     * @throws \RuntimeException
     */
    public function lock(): void
    {
        if ($this->kunci) {
            throw new \RuntimeException(__('This boning is already locked.'));
        }

        // Tiap boning WAJIB punya karkas.
        //
        // Syarat ini sempat dicabut karena disangka ada dokumen boning khusus
        // kulit dan offal yang tidak punya karkas. Itu salah baca: kulit dan
        // offal DIBERI LABEL DI DALAM boning yang sama, yang karkasnya memang
        // dipilih saat dokumennya dibuat. Project Owner:
        //
        //   "pas create boning kan kita pilih karkas mana yang di boning
        //    bahkan bisa pilih beberapa karkas"
        //
        // Jadi tidak pernah ada boning tanpa karkas, dan boning yang tidak
        // menyebut karkasnya adalah dokumen yang belum selesai dibuat.
        if ($this->carcasses()->doesntExist()) {
            throw new \RuntimeException(__('This boning has no carcass yet.'));
        }

        if ($this->items()->doesntExist()) {
            throw new \RuntimeException(__('This boning has no output goods yet.'));
        }

        $this->forceFill(['kunci' => true, 'status' => 'LOCKED'])->save();
    }

    public function unlock(): void
    {
        if (! $this->kunci) {
            throw new \RuntimeException(__('This boning is not locked.'));
        }

        $this->forceFill(['kunci' => false, 'status' => 'OPEN'])->save();
    }
}
