<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Pengeluaran kas kecil (issue #453) -- tanpa approval, kontrolnya izin +
 * jejak (LogsActivity) + daftar kasbon belum kembali yang selalu terlihat.
 *
 * Dua jenis: `advance` (kasbon, `Open` -> `Settled`/`Cancelled`) dan
 * `reimburse` (langsung `Settled`, tidak pernah `Open`). Biaya yang
 * dilaporkan = nominal nota (`receipt_amount`), bukan nominal uang yang
 * dikeluarkan -- keduanya beda saat kasbon.
 */
class Expense extends Model
{
    use SoftDeletes, LogsActivity;

    public const TYPE_ADVANCE = 'advance';

    public const TYPE_REIMBURSE = 'reimburse';

    public const STATUS_OPEN = 'Open';

    public const STATUS_SETTLED = 'Settled';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'expense_number',
        'expense_date',
        'type',
        'bank_account_id',
        'expense_category_id',
        'recipient_name',
        'user_id',
        'advance_amount',
        'receipt_amount',
        'settled_at',
        'settled_by',
        'status',
        'description',
        'receipt_photo',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'advance_amount' => 'decimal:2',
        'receipt_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /** Seluruh pergerakan kas yang lahir dari dokumen ini -- pemberian, pengembalian, tambahan. */
    public function transactions(): MorphMany
    {
        return $this->morphMany(BankTransaction::class, 'reference');
    }

    /**
     * Sisa yang harus disetor (positif) atau ditambah (negatif) -- turunan,
     * bukan kolom. Hanya berarti untuk `advance`; reimburse tidak pernah
     * punya uang muka untuk dibandingkan.
     */
    public function getBalanceDueAttribute(): ?float
    {
        if ($this->type !== self::TYPE_ADVANCE || $this->receipt_amount === null) {
            return null;
        }

        return round((float) $this->advance_amount - (float) $this->receipt_amount, 2);
    }

    /**
     * Buat kasbon: uang keluar sebesar `advance_amount`, status `Open`.
     *
     * @throws \RuntimeException
     */
    public static function createAdvance(array $data): self
    {
        if (empty($data['advance_amount']) || (float) $data['advance_amount'] <= 0) {
            throw new \RuntimeException(__('The advance amount is required.'));
        }

        return DB::transaction(function () use ($data) {
            $expense = static::create(array_merge($data, [
                'type' => self::TYPE_ADVANCE,
                'status' => self::STATUS_OPEN,
                'receipt_amount' => null,
                'settled_at' => null,
                'settled_by' => null,
            ]));

            BankTransaction::create([
                'bank_account_id' => $expense->bank_account_id,
                'type' => 'out',
                'amount' => $expense->advance_amount,
                'reference_type' => self::class,
                'reference_id' => $expense->id,
                'description' => __('Advance given for :number', ['number' => $expense->expense_number]),
                'transaction_date' => $expense->expense_date,
            ]);

            return $expense;
        });
    }

    /**
     * Buat reimburse: penerima sudah bayar sendiri, satu transaksi keluar
     * sebesar nota, langsung `Settled`.
     *
     * @throws \RuntimeException
     */
    public static function createReimburse(array $data): self
    {
        if (empty($data['receipt_amount']) || (float) $data['receipt_amount'] <= 0) {
            throw new \RuntimeException(__('The receipt amount is required for a reimbursement.'));
        }

        return DB::transaction(function () use ($data) {
            $expense = static::create(array_merge($data, [
                'type' => self::TYPE_REIMBURSE,
                'status' => self::STATUS_SETTLED,
                'advance_amount' => null,
                'settled_at' => now(),
                'settled_by' => Auth::id(),
            ]));

            BankTransaction::create([
                'bank_account_id' => $expense->bank_account_id,
                'type' => 'out',
                'amount' => $expense->receipt_amount,
                'reference_type' => self::class,
                'reference_id' => $expense->id,
                'description' => __('Reimbursement for :number', ['number' => $expense->expense_number]),
                'transaction_date' => $expense->expense_date,
            ]);

            return $expense;
        });
    }

    /**
     * Kembalikan kasbon: nota lebih kecil dari uang muka -> sisa masuk
     * kembali; nota lebih besar -> kekurangan keluar tambahan; sama persis
     * -> tidak ada transaksi kas baru sama sekali.
     *
     * @throws \RuntimeException
     */
    public function settle(float $receiptAmount): void
    {
        if ($receiptAmount < 0) {
            throw new \RuntimeException(__('The receipt amount cannot be negative.'));
        }

        DB::transaction(function () use ($receiptAmount) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== self::STATUS_OPEN) {
                throw new \RuntimeException(__('Only an open advance can be settled.'));
            }

            $balanceDue = round((float) $locked->advance_amount - $receiptAmount, 2);

            // Aman dari guard updating() -- status ASLI sebelum baris ini
            // masih Open, jadi tidak pernah tertahan sebagai "sudah
            // terkunci".
            $this->forceFill([
                'receipt_amount' => $receiptAmount,
                'status' => self::STATUS_SETTLED,
                'settled_at' => now(),
                'settled_by' => Auth::id(),
            ])->save();

            if ($balanceDue > 0) {
                BankTransaction::create([
                    'bank_account_id' => $this->bank_account_id,
                    'type' => 'in',
                    'amount' => $balanceDue,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'description' => __('Advance change returned for :number', ['number' => $this->expense_number]),
                    'transaction_date' => now()->toDateString(),
                ]);
            } elseif ($balanceDue < 0) {
                BankTransaction::create([
                    'bank_account_id' => $this->bank_account_id,
                    'type' => 'out',
                    'amount' => abs($balanceDue),
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'description' => __('Additional expense beyond advance for :number', ['number' => $this->expense_number]),
                    'transaction_date' => now()->toDateString(),
                ]);
            }
        });
    }

    /**
     * Batalkan kasbon yang masih Open: seluruh uang muka kembali, status
     * `Cancelled`. Baris dokumennya TETAP ADA (beda dari hapus).
     *
     * @throws \RuntimeException
     */
    public function cancel(): void
    {
        DB::transaction(function () {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== self::STATUS_OPEN) {
                throw new \RuntimeException(__('Only an open advance can be cancelled.'));
            }

            $this->refundAdvance(__('Advance cancelled for :number', ['number' => $this->expense_number]));

            $this->forceFill(['status' => self::STATUS_CANCELLED])->save();
        });
    }

    private function refundAdvance(string $reason): void
    {
        BankTransaction::create([
            'bank_account_id' => $this->bank_account_id,
            'type' => 'in',
            'amount' => $this->advance_amount,
            'reference_type' => self::class,
            'reference_id' => $this->id,
            'description' => $reason,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->expense_number)) {
                $model->expense_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'expense_number',
                    prefix: 'EXP#'.date('y'),
                    padding: 4,
                );
            }
            if (empty($model->bank_account_id)) {
                $model->bank_account_id = BankAccount::cashAccount()->id;
            }
            if (! empty($model->recipient_name)) {
                $model->recipient_name = strtoupper($model->recipient_name);
            }
            if (empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function (self $model) {
            // settle()/cancel() selalu bertransisi DARI Open, jadi status
            // ASLI di sini tidak pernah Settled/Cancelled saat keduanya
            // menyimpan -- guard ini murni menahan EDIT dari luar pada
            // dokumen yang sudah terkunci, bukan transisi resminya sendiri.
            if (in_array($model->getOriginal('status'), [self::STATUS_SETTLED, self::STATUS_CANCELLED], true)) {
                throw new \Exception(__('This expense is locked and can no longer be edited.'));
            }
        });

        static::deleting(function (self $model) {
            if ($model->status !== self::STATUS_OPEN) {
                throw new \Exception(__('Only an open expense can be deleted; settled or cancelled expenses are locked.'));
            }

            // "Hapus Open = batalkan" -- uangnya harus kembali sebelum
            // barisnya hilang, supaya tidak ada BankTransaction yatim
            // yang menunjuk ke dokumen yang sudah tidak ada.
            $model->refundAdvance(__('Advance returned because the expense :number was deleted', ['number' => $model->expense_number]));
        });
    }
}
