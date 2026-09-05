<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
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
}
