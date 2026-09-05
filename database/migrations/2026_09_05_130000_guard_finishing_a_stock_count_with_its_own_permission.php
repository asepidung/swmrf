<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelesaikan opname adalah kewenangan tersendiri.
 *
 * Tombol "Finish Opname" menghapus PERMANEN setiap baris berstatus MISSING
 * dari `beef_stocks` -- dan `BeefStock` tidak memakai hapus lunak, jadi
 * barisnya benar-benar hilang -- lalu menambahkan yang UNEXPECTED sebagai stok
 * baru.
 *
 * Sebelum ini satu-satunya syarat menampilkannya adalah statusnya
 * `IN_PROGRESS`. Artinya siapa pun yang boleh MELIHAT daftar opname boleh
 * menjalankannya. Melihat dan memutuskan adalah dua kewenangan yang berbeda;
 * bentuk yang sama sudah ditambal pada Approve retur, Lock repack, halaman
 * yang mencetak stok, dan hapus stok.
 *
 * Dibuat lewat MIGRASI, bukan seeder: `DatabaseSeeder` mengatur ulang kata
 * sandi superuser sehingga tidak boleh dijalankan di server, dan izin yang
 * hanya hidup di sana tidak pernah sampai ke sistem yang berjalan (#269).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'finish_stock_takes')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'finish_stock_takes',
            'module_name' => 'Stock Takes',
            'description' => 'Finish a stock count and apply it to stock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun: izinnya sudah bisa dilekatkan ke
        // pengguna, dan menghapusnya ikut memutus lekatan itu.
    }
};
