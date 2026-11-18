<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // KAS-01 (keputusan Owner): uang keluar yang membuat saldo kas/rekening minus hanya DIPERINGATKAN, tidak ditolak
        // (saldo awal bisa saja belum diisi). Satu tempat untuk semua jalur: kasbon, reimburse, bayar pemasok, dan lain-lain.
        static::created(function (BankTransaction $trx) {
            if ($trx->type !== 'out' || app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            try {
                $akun = $trx->bankAccount;
                $saldo = $akun?->currentBalance();

                if ($akun && $saldo < 0) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('This payment makes the balance of :account negative', ['account' => $akun->bank_name ?? '-']))
                        ->body(__('Balance now: :balance. Check that the opening balance and earlier entries are complete.', ['balance' => number_format($saldo, 0, ',', '.')]))
                        ->warning()->persistent()->send();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
