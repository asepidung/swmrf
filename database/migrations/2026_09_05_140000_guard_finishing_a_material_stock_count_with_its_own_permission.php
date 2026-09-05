<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelesaikan opname material adalah kewenangan tersendiri.
 *
 * Tombolnya menyesuaikan stok material secara PERMANEN sesuai hasil hitungan.
 * Sebelum ini syaratnya hanya status dokumennya -- siapa pun yang boleh
 * melihat daftar opname material bisa menjalankannya, lewat dua tombol
 * berbeda pula.
 *
 * Padanannya di sisi daging, `finish_stock_takes`, dibuat pada #283.
 *
 * Dibuat lewat MIGRASI, bukan seeder: seeder mengatur ulang kata sandi
 * superuser sehingga tidak boleh dijalankan di server, dan izin yang hanya
 * hidup di sana tidak pernah sampai ke sistem yang berjalan (#269).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'finish_material_stock_takes')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'finish_material_stock_takes',
            'module_name' => 'Material Stock Takes',
            'description' => 'Finish a material stock count and apply it to stock',
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
