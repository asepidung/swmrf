<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayableInstallment extends Model
{
    use HasFactory;

    protected $guarded = [];

    // --- RELASI ---

    // Relasi standar sesuai nama tabel
    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id');
    }

    // Alias relasi buat dipanggil di halaman Print Voucher
    public function payable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id');
    }

    // Relasi ke User (Kasir / Finance yang nginput)
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- LOGIKA OTOMATIS (LIFECYCLE EVENTS) ---

    protected static function booted()
    {
        static::created(function ($installment) {
            $installment->recalculateAPBalance();

            // --- LOGIKA LEDGER OTOMATIS ---
            if ($installment->company_bank_id) {
                // 1. Ambil saldo terakhir dari bank ini
                $lastLedger = BankLedger::where('company_bank_id', $installment->company_bank_id)
                    ->orderBy('id', 'desc')
                    ->first();

                $lastBalance = $lastLedger ? $lastLedger->balance_after : 0;
                $newBalance = $lastBalance - $installment->amount_paid;

                // 2. Insert ke Ledger
                BankLedger::create([
                    'company_bank_id' => $installment->company_bank_id,
                    'transaction_date' => $installment->payment_date,
                    'credit' => $installment->amount_paid,
                    'balance_after' => $newBalance,
                    'description' => "Payment for PO: " . ($installment->accountPayable->payable->po_number ?? '-'),
                    'referenceable_id' => $installment->id,
                    'referenceable_type' => get_class($installment),
                ]);
            }
        });

        // Tambahkan juga logic deleted jika ingin mutasi "dibatalkan"
        static::deleted(function ($installment) {
            if ($installment->accountPayable) {
                $installment->accountPayable->recalculateBalanceFromInstallments();
            }
            // Hapus mutasi terkait jika cicilan dihapus
            BankLedger::where('referenceable_id', $installment->id)
                ->where('referenceable_type', get_class($installment))
                ->delete();
        });
    }
    // Fungsi helper buat ngitung dan update ke tabel induk
    public function recalculateAPBalance()
    {
        // 1. Pastikan total reduction selalu update
        $reduction = (float)$this->amount_paid + (float)$this->discount_amount + (float)$this->tax_deduction_amount;
        $this->updateQuietly(['total_debt_reduction' => $reduction]);

        // 2. Update saldo di induk AP
        if ($this->accountPayable) {
            $this->accountPayable->recalculateBalanceFromInstallments();
        }
    }
}
