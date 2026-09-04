<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'payment_number',
        'customer_group_id',
        'bank_account_id',
        'payment_date',
        'amount',
        'total_deduction',
        'reference_number',
        'note',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
        'total_deduction' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Sudah dibatalkan atau belum. */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Pembayaran yang masih berlaku.
     *
     * Yang dibatalkan TETAP ADA -- itu inti dari membalik alih-alih menghapus.
     * Karena itu setiap penjumlahan uang wajib lewat penyaring ini; tanpanya
     * pembayaran yang sudah dibatalkan ikut terhitung dan angkanya terlalu
     * besar tanpa satu pun gejala.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * Batalkan pembayaran ini, dengan membalik seluruh akibatnya.
     *
     * Ada LIMA hal yang harus dikembalikan, dan melewatkan satu saja membuat
     * saldo bank atau piutang salah tanpa gejala:
     *
     *  1. alokasi ke tiap invoice -- `paid_amount` turun kembali;
     *  2. sisa tagihan dan status invoice -- lewat `recalculate()`;
     *  3. baris buku kas yang masuk -- dibalik dengan baris keluar;
     *  4. baris buku kas potongannya -- dibalik dengan baris masuk;
     *  5. pembayarannya sendiri -- DITANDAI batal, bukan dihapus.
     *
     * Baris buku kas aslinya tidak disentuh. Menghapusnya akan membuat buku
     * kas berbohong tentang masa lalu; yang benar adalah menambahkan lawannya,
     * supaya keduanya terbaca dan selisihnya nol.
     *
     * @throws \RuntimeException
     */
    public function cancel(string $reason, ?int $userId = null): void
    {
        if ($this->isCancelled()) {
            throw new \RuntimeException(__('This payment has already been cancelled.'));
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($reason, $userId): void {
            foreach ($this->allocations as $allocation) {
                $invoice = $allocation->invoice;

                if (! $invoice) {
                    continue;
                }

                $invoice->paid_amount = max(
                    (float) $invoice->paid_amount - (float) $allocation->amount_allocated,
                    0,
                );

                $invoice->recalculate();
                $invoice->save();
            }

            foreach ($this->cashBookLines() as $baris) {
                BankTransaction::create([
                    'bank_account_id' => $baris->bank_account_id,
                    'type' => $baris->type === 'in' ? 'out' : 'in',
                    'amount' => $baris->amount,
                    'reference_type' => static::CANCELLATION_REFERENCE,
                    'reference_id' => $this->id,
                    'description' => __('Cancellation of :number', ['number' => $this->payment_number]),
                    'transaction_date' => now()->toDateString(),
                ]);
            }

            $this->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'cancellation_reason' => $reason,
            ])->save();
        });
    }

    /**
     * Baris buku kas yang lahir dari pembayaran ini.
     *
     * Termasuk baris potongannya, yang menunjuk ke PaymentDeduction dan bukan
     * ke pembayarannya -- karena itu dicari lewat kedua jalur.
     *
     * SENGAJA TIDAK dinamai `bankTransactions()`. Eloquent memperlakukan
     * metode berpola nama relasi sebagai relasi begitu diakses sebagai
     * properti, dan gagal dengan `LogicException` karena yang dikembalikan
     * Collection, bukan Relation. Namanya dibuat jelas bukan relasi supaya
     * tidak ada yang tergoda menulis `$payment->bankTransactions`.
     */
    public function cashBookLines(): \Illuminate\Support\Collection
    {
        $idPotongan = $this->deductions->pluck('id');

        return BankTransaction::query()
            ->where(function ($query) use ($idPotongan) {
                $query->where(function ($q) {
                    $q->where('reference_type', static::class)->where('reference_id', $this->id);
                })->orWhere(function ($q) use ($idPotongan) {
                    $q->where('reference_type', PaymentDeduction::class)
                        ->whereIn('reference_id', $idPotongan);
                });
            })
            ->get();
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PaymentDeduction::class, 'payment_id');
    }

    /**
     * Awalan nomor bukti terima pembayaran pelanggan.
     *
     * PR = Payment Receipt. Bentuknya disamakan dengan dokumen lain di
     * aplikasi ini -- awalan, dua digit tahun, lalu urutannya di UJUNG --
     * supaya `DocumentNumber::next()` bisa dipakai apa adanya.
     *
     * Bentuk lamanya `PAY-0001/IX/26` menaruh urutan di TENGAH, sehingga
     * penomorannya harus dirakit sendiri, dan rakitan itulah yang punya dua
     * cara terulang. Keputusan Project Owner, 3 September 2026: PR# untuk
     * uang masuk, PV# untuk uang keluar -- awalannya langsung memberi tahu
     * arah uangnya.
     */
    public const NUMBER_PREFIX = 'PR#';

    /**
     * Penanda baris buku kas yang lahir dari PEMBATALAN.
     *
     * Bukan nama kelas, melainkan penanda tersendiri -- mengikuti pola yang
     * sudah dipakai saldo awal rekening (`BankAccount::OPENING_BALANCE_REFERENCE`).
     *
     * Tanpa penanda ini baris pembalik tidak bisa dibedakan dari baris
     * aslinya, dan pembatalan berikutnya akan ikut membalik baris pembaliknya
     * sendiri -- saldonya bergeser tanpa ada yang mengubah apa pun.
     */
    public const CANCELLATION_REFERENCE = 'payment_cancellation';

    /**
     * Nomor berikutnya, lewat satu-satunya penomoran dokumen di aplikasi ini.
     *
     * Nomor milik dokumen yang sudah dihapus TETAP dihitung: dokumen boleh
     * hilang, nomornya tidak boleh dipakai ulang.
     */
    public static function nextNumber(): string
    {
        return DocumentNumber::next(
            query: static::withTrashed(),
            column: 'payment_number',
            prefix: static::NUMBER_PREFIX.date('y'),
            padding: 4,
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->payment_number)) {
                $model->payment_number = static::nextNumber();
            }

            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
