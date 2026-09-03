<?php

namespace App\Models;

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
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
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

    /** Awalan nomor pembayaran, ditulis di satu tempat. */
    public const NUMBER_PREFIX = 'PAY-';

    /**
     * Nomor pembayaran berikutnya.
     *
     * Bentuknya `PAY-0001/IX/26` -- urutannya di TENGAH, bukan di ujung,
     * sehingga `DocumentNumber::next()` tidak bisa dipakai apa adanya. Yang
     * penting dijaga di sini sama persis dengan yang dijaga di sana.
     *
     * Rakitan sebelumnya punya dua cara gagal, keduanya berakhir dengan
     * nomor yang sudah dipakai dicoba lagi -- dan `payment_number` bertanda
     * unique, jadi akibatnya bukan salah nomor melainkan CRASH di tengah hari
     * kerja:
     *
     *  - ia mengambil pembayaran TERAKHIR menurut id, lalu membaca urutannya
     *    dengan regex. Kalau nomor baris itu saja yang tidak cocok -- data
     *    lama, impor, apa pun -- urutannya dikembalikan ke 1;
     *  - ia tidak menghitung baris yang sudah dihapus lunak, padahal dokumen
     *    boleh hilang dan nomornya tetap tidak boleh dipakai ulang.
     *
     * Sekarang yang diambil urutan TERBESAR di tahun berjalan, dari seluruh
     * baris termasuk yang terhapus. Satu baris yang tidak terbaca tidak lagi
     * bisa mengembalikan hitungannya ke nol.
     *
     * Tahunnya dibaca dari NOMORNYA sendiri, bukan dari `created_at`.
     * Dokumen bertanggal mundur akan salah kelompok kalau dipilah dengan
     * tanggal pembuatannya -- persoalan yang sama sudah diberesi di Invoice.
     *
     * `lockForUpdate()` hanya berlaku selama transaksi yang membukanya, dan
     * halaman penerimaan pembayaran memang membungkus penyimpanannya dalam
     * satu transaksi.
     */
    public static function nextNumber(): string
    {
        $year = date('y');

        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        $sequence = static::withTrashed()
            ->where('payment_number', 'like', static::NUMBER_PREFIX.'%/'.$year)
            ->lockForUpdate()
            ->pluck('payment_number')
            ->map(function (string $number): int {
                preg_match('#^'.preg_quote(static::NUMBER_PREFIX, '#').'(\d+)/#', $number, $match);

                return (int) ($match[1] ?? 0);
            })
            ->max() ?? 0;

        return sprintf(
            '%s%04d/%s/%s',
            static::NUMBER_PREFIX,
            $sequence + 1,
            $romanMonths[(int) date('n')],
            $year,
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
