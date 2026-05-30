<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountPayable extends Model
{
    use HasFactory;

    // Buka gerbang mass assignment
    protected $guarded = [];

    /**
     * RELASI UNIVERSAL (Polymorphic)
     * AP terhubung ke berbagai model PO: Logistic, Beef, Cattle
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relasi ke Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relasi ke history pembayaran / cicilan
     */
    public function installments(): HasMany
    {
        return $this->hasMany(AccountPayableInstallment::class, 'account_payable_id');
    }

    /**
     * Relasi ke pembuat data (Finance Admin/System)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- FUNGSI PERHITUNGAN (THE BRAIN) ---

    /**
     * Fungsi sakti untuk sinkronisasi saldo AP berdasarkan cicilan.
     * Dipanggil otomatis setiap kali ada Installment yang dibuat/diedit/dihapus.
     */
    public function recalculateBalanceFromInstallments()
    {
        // 1. Ambil total pengurangan hutang (Uang keluar + Diskon) dari semua cicilan
        $totalPaidSoFar = $this->installments()->sum('total_debt_reduction');

        // 2. Hitung sisa tagihan
        $newBalance = $this->total_amount - $totalPaidSoFar;

        // 3. Update database secara senyap (updateQuietly) 
        // agar tidak memicu event 'updated' yang bisa bikin looping sistem.
        $this->updateQuietly([
            'paid_amount' => $totalPaidSoFar,
            'balance_due' => $newBalance,
            // Status otomatis: PAID jika lunas, PARTIAL jika dicicil, UNPAID jika belum bayar
            'status' => $newBalance <= 0 ? 'PAID' : ($totalPaidSoFar > 0 ? 'PARTIAL' : 'UNPAID'),
        ]);
    }
}
