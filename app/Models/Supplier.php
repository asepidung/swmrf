<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Supplier extends Model
{
    use LogsActivity;

    /**
     * Supplier menyimpan rekening tujuan transfer DP/pembayaran
     * (`bank_name`, `account_number`, `account_name`) -- master data
     * sensitif yang sebelumnya tidak punya jejak audit sama sekali. Ubah
     * nomor rekening sengaja/salah ketik tidak bisa ditelusuri siapa/kapan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Tarif PPN, di satu tempat saja.
     *
     * Angka `0.11` sebelumnya ditulis di SEPULUH tempat -- model, layar,
     * halaman persetujuan, dan dua berkas cetak. Selama tarifnya tidak
     * berubah semuanya kebetulan sama; begitu berubah, yang terlewat tidak
     * akan mengeluh sedikit pun, cuma menghasilkan angka lain.
     */
    public const TARIF_PPN = 0.11;

    protected $fillable = [
        'name',
        'address',
        'pic',
        'phone',
        'top_days',
        'is_tax_11',
        'is_active',
        'supplied_goods',
        'bank_name',
        'account_number',
        'account_name',
    ];

    /**
     * Keputusan Owner, 7 September 2026: nama dan alamat pemasok wajib huruf
     * besar. Sebelumnya SupplierResource sama sekali tidak menerapkannya --
     * beda dengan Customer/CustomerGroup/CustomerSegment/dst yang sudah lama
     * begini.
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function setAddressAttribute($value)
    {
        $this->attributes['address'] = $value === null ? null : strtoupper(trim($value));
    }

    public function setPicAttribute($value)
    {
        $this->attributes['pic'] = $value === null ? null : strtoupper(trim($value));
    }

    public function setSuppliedGoodsAttribute($value)
    {
        $this->attributes['supplied_goods'] = $value === null ? null : strtoupper(trim($value));
    }

    public function setBankNameAttribute($value)
    {
        $this->attributes['bank_name'] = $value === null ? null : strtoupper(trim($value));
    }

    public function setAccountNameAttribute($value)
    {
        $this->attributes['account_name'] = $value === null ? null : strtoupper(trim($value));
    }

    /**
     * Pemasok ini memungut PPN atau tidak.
     *
     * Namanya di basis data `is_tax_11`, dan HANYA itu yang ada. Sisi
     * pembelian daging sempat menanyakan `has_tax` -- kolom yang tidak
     * pernah ada, tanpa accessor, tanpa migrasi. Eloquent menjawab `null`
     * untuk kolom yang tidak ada, jadi jawabannya selalu "tidak memungut",
     * tanpa galat apa pun: setiap permintaan pembelian daging ke pemasok PKP
     * tersimpan dengan PPN nol, sementara hutangnya (`Payable`) menghitung
     * PPN-nya dengan benar dari kolom yang sungguhan.
     *
     * Dua angka untuk transaksi yang sama, selisihnya persis sebelas persen.
     */
    public function isPkp(): bool
    {
        return (bool) $this->is_tax_11;
    }

    /** PPN atas sebuah dasar pengenaan; nol kalau pemasoknya bukan PKP. */
    public function ppnAtas(float|int|string $dasar): float
    {
        return $this->isPkp() ? round((float) $dasar * self::TARIF_PPN, 2) : 0.0;
    }

    public function cattleReceivings()
    {
        return $this->hasMany(CattleReceiving::class);
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Supplier ini masih dipakai, dan tidak boleh dihapus.
     *
     * `cattle_receivings.supplier_id` dan `supplier_payments.supplier_id`
     * memakai cascadeOnDelete() -- beda dari tabel supplier lain
     * (purchase_materials, goods_receipt_*, payables) yang RESTRICT.
     * Supplier yang belum pernah punya PO (jadi tidak tertahan FK RESTRICT
     * manapun) tapi sudah punya riwayat penerimaan sapi dan/atau DP yang
     * sudah dibayar akan lolos dihapus TANPA PENOLAKAN lewat
     * `MasterDataDeletion` sendirian -- dan sekaligus menghapus permanen
     * kedua riwayat itu lewat cascade. Pemeriksaan ini menahannya SEBELUM
     * baris manapun hilang, skema FK sengaja tidak diubah.
     */
    public function isInUse(): bool
    {
        return $this->cattleReceivings()->exists() || $this->supplierPayments()->exists();
    }
}
