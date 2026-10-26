<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'returned_amount',
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
        'returned_amount' => 'decimal:2',
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

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('id');
    }

    /** Jumlah seluruh barang -- uang muka (advance) atau nominal nota (reimburse) saat dibuat. */
    public function itemsTotal(): float
    {
        return round((float) $this->items()->sum('amount'), 2);
    }

    /**
     * Sisa kasbon yang masih harus dikembalikan pemohon: nota sudah dilaporkan lebih kecil dari uang
     * yang diberikan dan sebagian/seluruh selisihnya belum kembali. 0 untuk dokumen lain.
     */
    public function outstandingReturn(): float
    {
        if ($this->type !== self::TYPE_ADVANCE || $this->status !== self::STATUS_OPEN || $this->receipt_amount === null) {
            return 0.0;
        }

        return max(0.0, round((float) $this->advance_amount - (float) $this->receipt_amount - (float) $this->returned_amount, 2));
    }

    /**
     * Uang kasbon yang masih di tangan pemohon: seluruh uang muka kalau belum lapor, atau sisa yang belum
     * dikembalikan kalau notanya lebih kecil. Inilah "tanggungan pemohon" yang terus tampil.
     */
    public function stillWithRequester(): float
    {
        if ($this->awaitingReport()) {
            return round((float) $this->advance_amount, 2);
        }

        return $this->outstandingReturn();
    }

    /** Kasbon belum dilaporkan -- uang masih di tangan pemohon, nota belum ada. */
    public function awaitingReport(): bool
    {
        return $this->type === self::TYPE_ADVANCE
            && $this->status === self::STATUS_OPEN
            && $this->receipt_amount === null;
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
     * Rapikan baris barang dari form: buang yang kosong, wajib ada kategori, keterangan, dan harga > 0.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{expense_category_id: int, description: string, amount: float}>
     *
     * @throws \RuntimeException
     */
    private static function cleanItems(array $items): array
    {
        $clean = [];

        foreach ($items as $item) {
            if (empty($item['expense_category_id']) && trim((string) ($item['description'] ?? '')) === '' && empty($item['amount'])) {
                continue;
            }

            $amount = (float) ($item['amount'] ?? 0);

            if (empty($item['expense_category_id']) || trim((string) ($item['description'] ?? '')) === '' || $amount <= 0) {
                throw new \RuntimeException(__('Every item needs a category, a description, and a price above zero.'));
            }

            $clean[] = [
                'expense_category_id' => (int) $item['expense_category_id'],
                'description' => trim((string) $item['description']),
                'amount' => round($amount, 2),
            ];
        }

        if ($clean === []) {
            throw new \RuntimeException(__('Add at least one item.'));
        }

        return $clean;
    }

    private function writeItems(array $items): void
    {
        $this->items()->delete();

        foreach ($items as $item) {
            $this->items()->create($item);
        }
    }

    /**
     * Buat kasbon: uang keluar sebesar jumlah seluruh barang, status `Open`.
     *
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws \RuntimeException
     */
    public static function createAdvance(array $data, array $items): self
    {
        $items = static::cleanItems($items);

        return DB::transaction(function () use ($data, $items) {
            $expense = static::create(array_merge($data, [
                'type' => self::TYPE_ADVANCE,
                'status' => self::STATUS_OPEN,
                'advance_amount' => round(array_sum(array_column($items, 'amount')), 2),
                'receipt_amount' => null,
                'returned_amount' => 0,
                'settled_at' => null,
                'settled_by' => null,
            ]));

            $expense->writeItems($items);

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
     * Buat reimburse: pemohon sudah bayar sendiri, satu transaksi keluar
     * sebesar jumlah seluruh barang, langsung `Settled`.
     *
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws \RuntimeException
     */
    public static function createReimburse(array $data, array $items): self
    {
        $items = static::cleanItems($items);

        return DB::transaction(function () use ($data, $items) {
            $expense = static::create(array_merge($data, [
                'type' => self::TYPE_REIMBURSE,
                'status' => self::STATUS_SETTLED,
                'advance_amount' => null,
                'receipt_amount' => round(array_sum(array_column($items, 'amount')), 2),
                'returned_amount' => 0,
                'settled_at' => now(),
                'settled_by' => Auth::id(),
            ]));

            $expense->writeItems($items);

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
     * Laporan balik kasbon: pemohon membawa nota, barang disesuaikan dengan nota (nilai, boleh tambah/kurang
     * baris). Sama dengan uang yang diberikan -> `Settled`. Lebih besar -> kekurangan keluar tambahan dan
     * `Settled`. Lebih kecil -> tetap `Open` sampai sisanya kembali lewat `receiveReturn()`, sebagian atau
     * bertahap, dan selama itu tanggungan pemohon terus tampil.
     *
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws \RuntimeException
     */
    public function settle(array $items): void
    {
        $items = static::cleanItems($items);
        $receiptAmount = round(array_sum(array_column($items, 'amount')), 2);

        DB::transaction(function () use ($items, $receiptAmount) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== self::STATUS_OPEN || $locked->type !== self::TYPE_ADVANCE) {
                throw new \RuntimeException(__('Only an open advance can be settled.'));
            }

            if ($locked->receipt_amount !== null) {
                throw new \RuntimeException(__('This advance has already been reported; record the returned money instead.'));
            }

            $balanceDue = round((float) $locked->advance_amount - $receiptAmount, 2);

            $this->writeItems($items);

            // Aman dari guard updating() -- status ASLI sebelum baris ini
            // masih Open, jadi tidak pernah tertahan sebagai "sudah
            // terkunci".
            $this->forceFill(array_merge(
                ['receipt_amount' => $receiptAmount],
                $balanceDue > 0 ? [] : ['status' => self::STATUS_SETTLED, 'settled_at' => now(), 'settled_by' => Auth::id()],
            ))->save();

            if ($balanceDue < 0) {
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
     * Terima sisa kasbon yang dikembalikan pemohon -- boleh sebagian, boleh berkali-kali. Begitu seluruh
     * selisih kembali, dokumen menjadi `Settled`.
     *
     * @throws \RuntimeException
     */
    public function receiveReturn(float $amount): void
    {
        DB::transaction(function () use ($amount) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->outstandingReturn() <= 0) {
                throw new \RuntimeException(__('There is no money left to be returned for this advance.'));
            }

            $outstanding = $locked->outstandingReturn();

            if ($amount <= 0 || round($amount, 2) > $outstanding) {
                throw new \RuntimeException(__('The returned amount must be above zero and not more than the outstanding amount.'));
            }

            $returned = round((float) $locked->returned_amount + $amount, 2);
            $done = round($outstanding - $amount, 2) <= 0;

            $this->forceFill(array_merge(
                ['returned_amount' => $returned],
                $done ? ['status' => self::STATUS_SETTLED, 'settled_at' => now(), 'settled_by' => Auth::id()] : [],
            ))->save();

            BankTransaction::create([
                'bank_account_id' => $this->bank_account_id,
                'type' => 'in',
                'amount' => round($amount, 2),
                'reference_type' => self::class,
                'reference_id' => $this->id,
                'description' => __('Advance change returned for :number', ['number' => $this->expense_number]),
                'transaction_date' => now()->toDateString(),
            ]);
        });
    }

    /**
     * Batalkan kasbon yang masih Open dan BELUM dilaporkan: seluruh uang muka kembali, status
     * `Cancelled`. Baris dokumennya TETAP ADA (beda dari hapus). Yang sudah dilaporkan tidak bisa
     * dibatalkan -- sisanya diselesaikan lewat `receiveReturn()`.
     *
     * @throws \RuntimeException
     */
    public function cancel(): void
    {
        DB::transaction(function () {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== self::STATUS_OPEN || $locked->receipt_amount !== null) {
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
            if ($model->status !== self::STATUS_OPEN || $model->receipt_amount !== null) {
                throw new \Exception(__('Only an open expense can be deleted; settled or cancelled expenses are locked.'));
            }

            // "Hapus Open = batalkan" -- uangnya harus kembali sebelum
            // barisnya hilang, supaya tidak ada BankTransaction yatim
            // yang menunjuk ke dokumen yang sudah tidak ada.
            $model->refundAdvance(__('Advance returned because the expense :number was deleted', ['number' => $model->expense_number]));
        });
    }
}
