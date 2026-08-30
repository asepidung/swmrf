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

        // DP adalah uang yang SUNGGUH-SUNGGUH keluar, terlepas dari
        // pembahasan hutang (yang tetap baru lahir saat barang diterima).
        // Setiap SupplierPayment WAJIB tercatat sebagai pengeluaran, dari
        // jalur mana pun ia dibuat — makanya ini model event, bukan kode yang
        // dipanggil manual di tiap halaman yang gampang lupa memanggilnya.
        static::created(function (self $payment) {
            $payment->recordCashOutflow();
        });
    }

    /**
     * Catat DP ini sebagai pengeluaran di buku kas/bank.
     *
     * Transfer memakai rekening yang dipilih finance. Tunai memakai akun KAS
     * tunggal — lihat BankAccount::cashAccount() untuk alasannya.
     */
    protected function recordCashOutflow(): void
    {
        $bankAccount = $this->method === self::METHOD_TRANSFER
            ? $this->bankAccount
            : BankAccount::cashAccount();

        if (! $bankAccount) {
            // Data lama atau tidak lengkap (transfer tanpa rekening). Jangan
            // menciptakan baris kas untuk akun yang tidak diketahui -- tapi
            // jangan pula diam. Uang sudah keluar; kalau baris kasnya hilang
            // tanpa jejak, selisihnya baru ketahuan saat rekonsiliasi bank dan
            // tidak ada yang bisa menunjuk asalnya.
            \Illuminate\Support\Facades\Log::warning(
                'Pembayaran supplier tanpa rekening: uang keluar tidak tercatat di buku kas.',
                ['supplier_payment_id' => $this->id, 'payment_number' => $this->payment_number, 'amount' => $this->amount],
            );

            return;
        }

        BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'type' => 'out',
            'amount' => $this->amount,
            'reference_type' => static::class,
            'reference_id' => $this->id,
            'description' => 'Uang muka ke supplier ' . ($this->supplier->name ?? '-') . ' (' . $this->payment_number . ')',
            'transaction_date' => $this->payment_date,
        ]);

        $bankAccount->decrement('balance', $this->amount);
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
     * Lepaskan kembali sebagian alokasi, hingga sebanyak $maximum.
     *
     * Dipakai saat sebuah utang dibatalkan (GR dibuka kuncinya). Tanpa ini
     * uang muka tercatat "sudah terpakai" untuk utang yang sudah tidak ada,
     * sehingga hilang permanen: utang berikutnya lahir sebesar nilai penuh
     * seolah DP-nya tidak pernah dibayar.
     *
     * @return float nilai yang benar-benar dilepas
     */
    public function releaseAmount(float $maximum): float
    {
        $released = min((float) $this->allocated_amount, max($maximum, 0));

        if ($released <= 0) {
            return 0.0;
        }

        $this->allocated_amount = (float) $this->allocated_amount - $released;
        $this->save();

        return $released;
    }

    /**
     * Uang muka milik satu dokumen asal yang SUDAH terpakai sebagian.
     *
     * Diurutkan terbalik dari unallocatedFor(): yang paling belakang
     * dialokasikan, itu yang paling dulu dilepas.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function allocatedFor(Model $source)
    {
        return static::query()
            ->where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->where('allocated_amount', '>', 0)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();
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
