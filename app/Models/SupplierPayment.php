<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Pembayaran ke supplier, termasuk uang muka (DP) yang dibayar saat order.
 *
 * Utang lahir saat barang diterima, sementara DP dibayar saat order — jadi saat
 * DP dicatat, utangnya belum ada. Selisih antara `amount` dan `allocated_amount`
 * adalah uang muka yang masih menggantung dan menunggu utangnya terbit.
 */
class SupplierPayment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const METHOD_CASH = 'cash';

    public const METHOD_TRANSFER = 'transfer';

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'source_type',
        'source_id',
        'payment_date',
        'method',
        'bank_account_id',
        'reference_number',
        'amount',
        'allocated_amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = static::generateNumber();
            }

            if (empty($payment->created_by)) {
                $payment->created_by = auth()->id();
            }
        });
    }

    public static function generateNumber(): string
    {
        $year = date('y');
        $prefix = 'SP#' . $year;

        // Dikunci supaya dua pembayaran yang dibuat bersamaan tidak memperoleh
        // nomor yang sama, mengikuti pola generator dokumen lain di proyek ini.
        $last = static::withTrashed()
            ->where('payment_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($last && preg_match('/(\d{4})$/', $last->payment_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Uang muka yang belum dipotongkan ke utang mana pun. */
    public function getUnallocatedAmountAttribute(): float
    {
        return round((float) $this->amount - (float) $this->allocated_amount, 2);
    }

    public function isFullyAllocated(): bool
    {
        return $this->unallocated_amount <= 0;
    }

    /**
     * Potongkan uang muka yang masih menggantung ke sebuah utang.
     *
     * Dikembalikan nilai yang benar-benar terpakai, supaya pemanggilnya bisa
     * menjumlahkan beberapa pembayaran sekaligus tanpa melebihi nilai utang.
     */
    public function allocateTo(Payable $payable, float $maximum): float
    {
        $applied = min($this->unallocated_amount, $maximum);

        if ($applied <= 0) {
            return 0.0;
        }

        $this->allocated_amount = (float) $this->allocated_amount + $applied;
        $this->save();

        return $applied;
    }

    /**
     * Uang muka milik satu dokumen asal yang belum habis dipotongkan.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function unallocatedFor(Model $source)
    {
        return static::query()
            ->where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->whereColumn('allocated_amount', '<', 'amount')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->payment_number ?? 'Supplier Payment');
    }
}
