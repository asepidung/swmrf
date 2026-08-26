<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    /** Penanda akun kas tunai. Bukan rekening bank sungguhan. */
    public const CASH_INITIAL = 'KAS';

    protected $fillable = [
        'initial',
        'bank_name',
        'account_number',
        'account_holder',
        'is_active',
        'balance',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Akun kas tunai, dibuat sekali dan dipakai seterusnya.
     *
     * Uang keluar SELALU harus tercatat, tunai maupun transfer, tapi
     * `bank_transactions.bank_account_id` tidak boleh NULL — sebuah kas
     * tunai memang sebuah akun, sama seperti rekening bank. Dikunci dengan
     * `->lockForUpdate()`, mengikuti pola generator nomor dokumen lain di
     * proyek ini, supaya dua pembayaran tunai bersamaan tidak menciptakan
     * dua baris KAS.
     */
    public static function cashAccount(): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $existing = static::where('initial', self::CASH_INITIAL)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            return static::create([
                'initial' => self::CASH_INITIAL,
                'bank_name' => 'Kas Tunai',
                'account_number' => '-',
                'account_holder' => 'Wijaya Meat',
                'is_active' => true,
            ]);
        });
    }
}
