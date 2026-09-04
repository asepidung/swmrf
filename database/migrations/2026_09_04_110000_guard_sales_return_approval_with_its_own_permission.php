<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * Menyetujui dan membuka kunci retur penjualan punya izinnya sendiri.
 *
 * Kedua tombol itu MENGGERAKKAN STOK: Approve memasukkan setiap barang retur
 * ke gudang, Unlock menariknya kembali keluar. Sampai 4 September 2026
 * keduanya hanya dijaga `edit_sales_returns` -- izin yang sama dengan
 * membetulkan catatan atau mengganti tanggal.
 *
 * Bentuknya persis seperti halaman Terima Pembayaran sebelum diberi izin
 * sendiri: kewenangan yang mengubah angka sungguhan menumpang kewenangan yang
 * hanya merapikan dokumen.
 *
 * Dipisah menjadi DUA, bukan satu. Menyetujui menambah stok; membukanya
 * kembali MENGHAPUS baris stok yang sudah ada. Yang kedua lebih berbahaya dan
 * biasanya dipegang lebih sedikit orang.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'approve_sales_returns',
            'module_name' => 'Sales Returns',
            'description' => 'Approve a sales return and put its goods into stock',
        ],
        [
            'name' => 'unlock_sales_returns',
            'module_name' => 'Sales Returns',
            'description' => 'Unlock an approved sales return and pull its goods back out of stock',
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->delete();
    }
};
