<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat temuan material adalah kewenangan tersendiri.
 *
 * Modul Temuan Material menambah baris `material_stocks` dari isian orang,
 * tanpa dokumen asal apa pun -- padanan `FoundItemScanner` di sisi bahan,
 * yang sudah diberi izinnya sendiri pada #269.
 *
 * Sebelum ini modul itu TIDAK PUNYA policy dan TIDAK PUNYA satu pun izin.
 * Laravel mengizinkan apa saja ketika sebuah model tidak punya policy, jadi
 * siapa pun yang bisa membuka rumpun Materials Stock -- termasuk yang hanya
 * diberi `view_material_stocks` -- bisa menambah stok bahan sebanyak apa pun,
 * lalu menghapusnya lagi dan menariknya kembali.
 *
 * Dibuat lewat MIGRASI, bukan seeder: seeder mengatur ulang kata sandi
 * superuser sehingga tidak boleh dijalankan di server, dan izin yang hanya
 * hidup di sana tidak pernah sampai ke sistem yang berjalan (#269).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'record_material_findings')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'record_material_findings',
            'module_name' => 'Material Findings',
            'description' => 'Record material findings',
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
