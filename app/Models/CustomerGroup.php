<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerGroup extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'head_office_address',
        'head_office_pic',
        'top',
    ];

    /**
     * Master data yang menentukan uang (TOP grup, diskon per grup lewat
     * PriceList) -- wajib memakai activity log seperti Warehouse/Grade.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    /** Keputusan Owner, 7 September 2026: alamat dan PIC kantor pusat ikut wajib huruf besar. */
    public function setHeadOfficeAddressAttribute($value)
    {
        $this->attributes['head_office_address'] = $value === null ? null : strtoupper(trim($value));
    }

    public function setHeadOfficePicAttribute($value)
    {
        $this->attributes['head_office_pic'] = $value === null ? null : strtoupper(trim($value));
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Grup ini masih dipakai, dan tidak boleh dihapus.
     *
     * `customers.customer_group_id` (nullOnDelete) dan
     * `price_lists.customer_group_id` (cascadeOnDelete) TIDAK melempar galat
     * apa pun saat grupnya dihapus -- skema DB diam-diam menjadikannya NULL
     * atau ikut menghapus PriceList beserta seluruh itemnya. `receivables`
     * juga nullOnDelete. Hanya `payments.customer_group_id` yang RESTRICT.
     * Karena tiga dari empat relasi ini tidak akan pernah melempar
     * `QueryException`, `MasterDataDeletion` sendirian tidak cukup --
     * pemeriksaan bisnis ini yang menahannya SEBELUM baris manapun hilang.
     */
    public function isInUse(): bool
    {
        return $this->customers()->exists()
            || $this->priceList()->exists()
            || $this->receivables()->exists()
            || $this->payments()->exists();
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
     * Alokasi pembayaran milik grup ini, lewat pembayarannya.
     *
     * Dipakai untuk menghitung deposit dalam SATU kueri di daftar piutang --
     * tanpa relasi ini, tiap baris grup harus ditanya sendiri-sendiri.
     */
    public function paymentAllocations(): HasManyThrough
    {
        return $this->hasManyThrough(
            PaymentAllocation::class,
            Payment::class,
            'customer_group_id',
            'payment_id',
            'id',
            'id',
        );
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
