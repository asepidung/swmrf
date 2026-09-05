<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk tombol-tombol yang selama ini hanya dijaga STATUS dokumennya.
 *
 * Pemindaian seluruh `app/` menemukan enam belas aksi yang mengubah keadaan
 * tanpa satu pun pemeriksaan izin. Sebagian memang sudah diputuskan Owner
 * untuk dibiarkan (kirim/terima mutasi), sebagian lagi tidak berbahaya. Yang
 * di bawah ini punya akibat uang atau stok:
 *
 *  - **Kunci/buka kunci GR.** Mengunci GR MENERBITKAN hutang ke pemasok;
 *    membukanya kembali MENGHAPUS hutang itu. Sebelum ini syaratnya hanya
 *    "dokumennya punya barang".
 *
 *  - **Setujui/batalkan persetujuan Surat Jalan.** Membatalkan persetujuan
 *    menghapus tanda terimanya dan mengembalikan barang tolakan ke Tally --
 *    memutar balik sebuah pengiriman.
 *
 *  - **Batalkan Purchase Order.** Menutup pesanan yang sudah terbit.
 *
 * Penamaannya mengikuti yang sudah ada: `lock_bonings`, `lock_repacks`,
 * `lock_tallies`.
 *
 * Dibuat lewat MIGRASI, bukan seeder: seeder mengatur ulang kata sandi
 * superuser sehingga tidak boleh dijalankan di server (#269).
 */
return new class extends Migration
{
    private const IZIN = [
        ['lock_goods_receipt_products', 'GR Beef', 'Lock/Unlock goods receipt beef'],
        ['lock_gr_materials', 'GR Material', 'Lock/Unlock goods receipt material'],
        ['approve_delivery_orders', 'Delivery Orders', 'Approve delivery orders'],
        ['cancel_purchase_products', 'PO Beef', 'Cancel purchase order beef'],
        ['cancel_purchase_materials', 'PO Material', 'Cancel purchase order material'],
    ];

    public function up(): void
    {
        foreach (self::IZIN as [$name, $module, $description]) {
            if (DB::table('permissions')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $name,
                'module_name' => $module,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun: izinnya sudah bisa dilekatkan ke
        // pengguna, dan menghapusnya ikut memutus lekatan itu.
    }
};
