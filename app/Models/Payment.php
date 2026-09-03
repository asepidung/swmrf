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
