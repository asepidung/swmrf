<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerGroup extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'head_office_address',
        'head_office_pic',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function priceList()
    {
        return $this->hasOne(PriceList::class);
    }

    public function receivables()
    {
        return $this->hasMany(Receivable::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Deposit grup ini: uang yang sudah diterima tetapi belum menutup tagihan.
     *
     * Lahir dari pelanggan yang mentransfer lebih besar daripada piutangnya --
     * dulu ditolak di pintu karena tidak ada tempat menaruhnya, sehingga
     * kelebihannya harus diurus di luar sistem.
     *
     * @return \Illuminate\Support\Collection<int, Payment>
     */
    public function depositPayments(): \Illuminate\Support\Collection
    {
        return $this->payments()
            ->active()
            ->with('allocations')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Payment $payment): bool => $payment->unallocatedAmount() > 0)
            ->values();
    }

    /** Seluruh depositnya, dalam rupiah. */
    public function availableDeposit(): float
    {
        return round(
            $this->depositPayments()->sum(fn (Payment $payment): float => $payment->unallocatedAmount()),
            2,
        );
    }

    /**
     * Invoice milik grup ini, lewat baris piutangnya.
     *
     * Dibuat supaya nominal dan HITUNGAN invoice di daftar piutang memakai
     * satu aturan yang sama. Sebelumnya nominalnya dijumlahkan lewat `join`
     * mentah -- yang MELEWATI penyaring hapus-lunak invoice -- sementara
     * hitungannya memakai `whereHas` yang MENERAPKANNYA. Satu grup bisa
     * menampilkan "Rp 5.000.000 / 0 Inv": dua angka bersebelahan yang saling
     * membantah.
     *
     * HasManyThrough menerapkan penyaring hapus-lunak pada keduanya --
     * baris piutangnya maupun invoicenya -- jadi pertanyaannya tidak bisa
     * lagi dijawab dua cara.
     */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(
            Invoice::class,
            Receivable::class,
            'customer_group_id',
            'id',
            'id',
            'invoice_id',
        );
    }
}
