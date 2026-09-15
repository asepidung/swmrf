<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Repack extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'repacks';

    protected $fillable = [
        'doc_no', 'repack_date', 'status', 'kunci', 'note', 'created_by',
        'yield_override_reason', 'yield_override_by', 'yield_override_at',
    ];

    protected $casts = [
        'repack_date' => 'date',
        'kunci' => 'boolean',
        'yield_override_at' => 'datetime',
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
                $currentYear = date('Y');
                $model->doc_no = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'doc_no',
                    prefix: 'RP#'.date('y'),
                    padding: 3,
                );
            }

            if (empty($model->status)) {
                $model->status = 'OPEN';
            }
        });

        static::deleted(function ($model) {
            if ($model->isForceDeleting()) {
                $model->financialLoss()->forceDelete();
            } else {
                $model->financialLoss()->delete();
            }
        });

        static::restored(function ($model) {
            if ($model->financialLoss()->withTrashed()->exists()) {
                $model->financialLoss()->withTrashed()->restore();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Laporan QC yang mendampingi dokumen ini. */
    public function qcReports(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\QcReport::class, 'reportable');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(RepackMaterial::class, 'repack_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(RepackResult::class, 'repack_id');
    }

    public function materialUsages(): MorphMany
    {
        return $this->morphMany(MaterialUsage::class, 'usageable');
    }

    public function yieldOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yield_override_by');
    }

    public function financialLoss(): MorphOne
    {
        return $this->morphOne(FinancialLoss::class, 'lossable');
    }

    // =================================================================
    // Hasil: apa yang masuk dibanding apa yang keluar
    // =================================================================

    /**
     * Berat bahan yang masuk ke dokumen ini.
     *
     * DIHITUNG dari barisnya, tidak disimpan. Menyimpannya berarti ada angka
     * kedua yang bisa menyimpang dari barisnya sendiri -- pola yang sudah
     * berkali-kali menggigit proyek ini: saldo bank, sisa tagihan invoice,
     * saldo hutang.
     */
    public function inputWeight(): float
    {
        return round((float) $this->materials()->sum('weight'), 2);
    }

    /** Berat hasil yang keluar dari dokumen ini. */
    public function outputWeight(): float
    {
        return round((float) $this->results()->sum('weight'), 2);
    }

    /**
     * Selisihnya. POSITIF berarti susut, NEGATIF berarti hasilnya lebih berat
     * daripada bahannya -- dan yang kedua itu mustahil secara fisik, jadi ia
     * pertanda salah ketik, bukan pertanda untung.
     */
    public function shrinkWeight(): float
    {
        return round($this->inputWeight() - $this->outputWeight(), 2);
    }

    /**
     * Susutnya dalam persen, atau `null` kalau belum ada bahan sama sekali.
     *
     * `null` di sini berarti "belum bisa dihitung", bukan "nol persen". Nol
     * persen adalah dokumen yang bahannya utuh menjadi hasil; belum ada bahan
     * adalah dokumen yang belum dikerjakan.
     */
    public function shrinkPercent(): ?float
    {
        $masuk = $this->inputWeight();

        if ($masuk <= 0) {
            return null;
        }

        return round(($this->shrinkWeight() / $masuk) * 100, 2);
    }

    /**
     * Ambang susut wajar yang berlaku, atau `null` kalau BELUM DISETEL.
     *
     * `null` adalah keadaan bawaan dan artinya penting: belum ada manusia yang
     * memilih angkanya, jadi tidak ada yang berhak menghalangi pekerjaan.
     */
    public static function shrinkLimitPercent(): ?float
    {
        return Setting::number(Setting::REPACK_MAX_SHRINK_PERCENT);
    }

    /**
     * Susut dokumen ini masih di dalam batas wajar?
     *
     * TRUE juga ketika ambangnya belum disetel -- gerbangnya memang belum
     * menyala. Menghalangi pekerjaan dengan angka yang tidak dipilih manusia
     * mana pun adalah kesalahan yang sudah dibuat pada penjaga berat retur,
     * dan tidak diulang di sini.
     *
     * Hasil yang LEBIH BERAT daripada bahannya selalu di luar batas, berapa
     * pun ambangnya. Itu mustahil secara fisik; tidak ada persentase yang bisa
     * membenarkannya.
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

    /** Sudah pernah ditembus, dan alasannya tercatat. */
    public function shrinkLimitWasOverridden(): bool
    {
        return $this->yield_override_at !== null;
    }

    /**
     * QC mengizinkan dokumen ini dikunci walaupun susutnya di luar batas.
     *
     * Keputusan Owner, 7 September 2026: yang mengerjakan repack TIDAK bisa
     * mengunci dokumen yang susutnya di luar batas. Ia harus mendapat izin
     * dari QC lebih dulu, dan QC wajib menuliskan alasannya. Sesudah itu,
     * barulah dokumennya bisa dikunci.
     *
     * **Izinnya diberikan LEBIH DULU, bukan diketik saat mengunci.** Bentuk
     * sebelumnya meminta alasan di dalam kotak Lock, sehingga siapa pun yang
     * memegang izinnya bisa menembus batas itu sendirian -- termasuk orang
     * yang membuat repacknya. Pemeriksaan yang ditandatangani sendiri oleh
     * yang diperiksa bukan pemeriksaan.
     *
     * @throws \RuntimeException
     */
    public function grantShrinkOverride(string $reason, ?int $userId = null): void
    {
        DB::transaction(function () use ($reason, $userId): void {
            // Baris dikunci dan dibaca ULANG dari basis data sebelum
            // ditulis -- tanpa ini, dua persetujuan QC yang bersamaan (atau
            // persetujuan yang bersamaan dengan material/hasil yang baru
            // saja berubah dan mencabut izin sebelumnya) bisa sama-sama
            // lolos pemeriksaan `kunci` yang membaca state PHP, bukan baris
            // yang sungguh dikunci.
            $locked = static::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->kunci) {
                throw new \RuntimeException(__('This repack is already locked.'));
            }

            if ($this->isWithinShrinkLimit()) {
                throw new \RuntimeException(__('The shrinkage of this repack is still within the limit, so it needs no approval.'));
            }

            if (trim($reason) === '') {
                throw new \RuntimeException(__('An approval must say why.'));
            }

            $locked->forceFill([
                'yield_override_reason' => trim($reason),
                'yield_override_by' => $userId ?? Auth::id(),
                'yield_override_at' => now(),
            ])->save();

            $this->yield_override_reason = $locked->yield_override_reason;
            $this->yield_override_by = $locked->yield_override_by;
            $this->yield_override_at = $locked->yield_override_at;
        });
    }

    /**
     * Mencabut izin yang pernah diberikan.
     *
     * Dipanggil setiap kali bahan atau hasilnya berubah. Izin QC menyertai
     * ANGKA yang dilihat QC saat memberikannya; begitu angkanya berubah,
     * izinnya tidak lagi menjelaskan apa pun.
     *
     * Tanpa ini, QC bisa mengizinkan susut 12% lalu ada yang menyunting
     * dokumennya menjadi 40% dan menguncinya dengan izin yang sama -- tanpa
     * satu pun gejala.
     */
    public function withdrawShrinkOverride(): void
    {
        if ($this->yield_override_at === null) {
            return;
        }

        $this->forceFill([
            'yield_override_reason' => null,
            'yield_override_by' => null,
            'yield_override_at' => null,
        ])->save();
    }

    /**
     * Kunci dokumen ini.
     *
     * SATU RUMAH untuk seluruh syaratnya, supaya tidak terulang pola yang
     * ditemukan di Retur Jual: satu aturan disalin ke tiga halaman dengan
     * penjagaan yang berbeda-beda, sehingga menambal satu meninggalkan dua
     * lainnya terbuka.
     *
     * Izin TIDAK diperiksa di sini. Model memegang aturannya, halaman
     * memegang kewenangannya.
     *
     * Susut di luar batas menuntut izin QC yang SUDAH diberikan lebih dulu
     * lewat `grantShrinkOverride()`. Alasannya tidak lagi diketik di sini:
     * yang mengunci adalah yang mengerjakan, dan yang mengizinkan adalah QC.
     *
     * @throws \RuntimeException
     */
    public function lock(): void
    {
        DB::transaction(function (): void {
            // Baris dikunci dan dibaca ulang sebelum syarat-syaratnya
            // diperiksa -- alasan yang sama dengan grantShrinkOverride():
            // dua klik Lock yang bersamaan tidak boleh sama-sama lolos
            // pemeriksaan `kunci` yang membaca state PHP, bukan baris yang
            // sungguh dikunci.
            $locked = static::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->kunci) {
                throw new \RuntimeException(__('This repack is already locked.'));
            }

            if ($this->materials()->doesntExist()) {
                throw new \RuntimeException(__('This repack has no input goods yet.'));
            }

            if ($this->results()->doesntExist()) {
                throw new \RuntimeException(__('This repack has no output goods yet.'));
            }

            $menembus = ! $this->isWithinShrinkLimit();

            if ($menembus && ! $this->shrinkLimitWasOverridden()) {
                throw new \RuntimeException(__('The shrinkage of this repack is outside the reasonable limit. QC has to approve it before it can be locked.'));
            }

            $locked->forceFill([
                'kunci' => true,
                'status' => 'LOCKED',
                // Yang tidak menembus tidak menyimpan jejak izin apa pun --
                // termasuk kalau sebelumnya sempat ada lalu angkanya
                // diperbaiki sehingga kembali wajar.
                'yield_override_reason' => $menembus ? $this->yield_override_reason : null,
                'yield_override_by' => $menembus ? $this->yield_override_by : null,
                'yield_override_at' => $menembus ? $this->yield_override_at : null,
            ])->save();

            // Kilogramnya masuk ke KOLOMNYA SENDIRI, bukan cuma dihitung
            // ulang setiap kali dibaca -- pola yang sama dengan susut kirim
            // (DeliveryOrder) dan susut timbang sapi (CattleWeighing).
            //
            // `amount` MASIH nol, dan itu disengaja. Menilai susut repack
            // dengan harga jual melebih-lebihkan: perusahaan tidak kehilangan
            // sebesar harga jual, melainkan sebesar modalnya ditambah margin
            // yang tidak jadi didapat. Angka yang benar HPP, dan HPP menunggu
            // B.O.M. Saat itu tiba, rupiahnya tinggal `quantity x HPP` dari
            // kolom ini -- tanpa menggali ulang catatan lama.
            //
            // Syaratnya berat susut (`shrinkWeight() > 0`), bukan rupiah --
            // rupiahnya memang akan selalu nol sampai HPP ada, jadi memakai
            // `amount > 0` sebagai syarat akan membuat baris ini tidak pernah
            // tertulis sama sekali (jebakan yang sudah pernah terjadi di
            // CattleWeighing, #299).
            $susut = $this->shrinkWeight();

            if ($susut > 0) {
                $this->financialLoss()->updateOrCreate(
                    [
                        'transaction_type' => FinancialLoss::SUMBER_REPACK,
                        'reference_number' => $this->doc_no,
                    ],
                    [
                        'date' => $this->repack_date,
                        'amount' => 0.00,
                        'quantity' => $susut,
                        'unit' => 'Kg',
                        'note' => __('Repack shrinkage on :document', [
                            'document' => $this->doc_no,
                        ]),
                    ]
                );
            } else {
                $this->financialLoss()->delete();
            }

            $this->kunci = true;
            $this->status = 'LOCKED';
            $this->yield_override_reason = $locked->yield_override_reason;
            $this->yield_override_by = $locked->yield_override_by;
            $this->yield_override_at = $locked->yield_override_at;
        });
    }

    /**
     * Buka kuncinya, beserta jejak penembusannya DAN kerugian yang sempat
     * tercatat.
     *
     * DITOLAK kalau salah satu hasilnya sudah tidak ada lagi di gudang.
     * Begitu satu barcode hasil Repack ini dipakai lagi di modul lain
     * (dikirim, jadi bahan Repack lain, direlabel, dst), membuka kunci dan
     * mengizinkan bahan/hasilnya diubah membuat riwayat produksinya tidak
     * lagi cocok dengan apa yang sungguh terjadi ke barang itu -- pola yang
     * sama persis dengan `SalesReturn::unlock()`: semua barang diperiksa
     * LEBIH DULU, baru dokumennya dibuka, supaya tidak berhenti di tengah
     * karena satu barang sudah terlanjur pindah.
     *
     * `RepackResult` sendiri BUKAN baris stok -- ia riwayat produksi yang
     * permanen, disambungkan ke `BeefStock` cuma lewat kesamaan `barcode`
     * (dibuat sepasang saat Input Hasil, lihat `InputHasilRepack::create()`).
     * Jadi "sudah dipakai" berarti barisnya sudah TIDAK ADA lagi di
     * `beef_stocks` (modul lain menghapusnya begitu barang keluar gudang,
     * bukan mengubah `status`-nya) -- itulah yang diperiksa di sini.
     *
     * Baris `FinancialLoss` yang ditulis `lock()` mengandalkan dokumennya
     * FINAL -- begitu dibuka lagi, bahan/hasilnya bisa berubah sama sekali
     * sebelum dikunci ulang (atau tidak pernah dikunci ulang sama sekali).
     * Membiarkan barisnya berdiri berarti laporan kerugian menampilkan
     * susut yang belum pasti terjadi. Pola yang sama dengan "Unapprove"
     * pada Delivery Order (`ViewDeliveryOrder.php`) -- membatalkan
     * finalisasi menghapus kerugian yang menyertainya. Kalau dikunci lagi,
     * `lock()` menulis ulang dari angka yang berlaku saat itu.
     *
     * Beda dari `SalesReturn::unlock()`: baris `BeefStock` di sini TIDAK
     * ditarik/dihapus. Buka kunci Repack cuma berarti "izinkan koreksi",
     * bukan "batalkan seluruh produksinya" -- kartonnya tetap ada di
     * gudang apa adanya, cuma dokumennya yang jadi bisa diedit lagi.
     *
     * @throws \RuntimeException
     */
    public function unlock(): void
    {
        DB::transaction(function (): void {
            // Alasan sama dengan lock(): baris dikunci dan dibaca ulang
            // sebelum diperiksa, supaya dua klik Unlock bersamaan tidak
            // sama-sama lolos pemeriksaan `kunci` yang membaca state PHP.
            $locked = static::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->kunci) {
                throw new \RuntimeException(__('This repack is not locked.'));
            }

            foreach ($this->results as $result) {
                $stock = BeefStock::where('barcode', $result->barcode)->lockForUpdate()->first();

                if (! $stock) {
                    throw new \RuntimeException(__('Item :barcode is no longer in stock (already used or shipped).', [
                        'barcode' => $result->barcode,
                    ]));
                }

                if ($stock->status !== 'IN_STOCK') {
                    throw new \RuntimeException(__('Item :barcode is no longer in the warehouse (status: :status).', [
                        'barcode' => $result->barcode,
                        'status' => $stock->status,
                    ]));
                }
            }

            $this->financialLoss()->delete();

            $locked->forceFill([
                'kunci' => false,
                'status' => 'OPEN',
                // Jejak penembusan ikut dilepas: begitu dokumennya bisa diubah
                // lagi, alasan yang dulu menyertai angka lama tidak lagi
                // menjelaskan angka yang sekarang.
                'yield_override_reason' => null,
                'yield_override_by' => null,
                'yield_override_at' => null,
            ])->save();

            $this->kunci = false;
            $this->status = 'OPEN';
            $this->yield_override_reason = null;
            $this->yield_override_by = null;
            $this->yield_override_at = null;
        });
    }
}
