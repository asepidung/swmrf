<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `pay_purchase_materials` -- satu lagi yang tertinggal di seeder.
 *
 * Saudaranya, `pay_purchase_products`, sudah dibuatkan migrasi pada
 * 5 September. Yang untuk MATERIAL tidak ikut terbawa, dan bentuk
 * kesalahannya persis sama seperti yang dicatat di migrasi itu: barisnya
 * tertulis rapi di `DatabaseSeeder`, tetapi `db:seed` tidak boleh dijalankan
 * di sistem yang sudah berjalan karena ia menyetel ulang kata sandi
 * superuser. Izinnya karena itu tidak pernah sampai ke sana.
 *
 * Gejalanya bukan error, melainkan tombol Bayar di PO Material yang tidak
 * pernah muncul untuk siapa pun kecuali programmer -- dan tidak ada centang
 * yang bisa diberikan untuk memunculkannya.
 *
 * Dua daftar yang sama ditulis di dua tempat memang selalu berakhir begini.
 * Yang menahan kejadian berikutnya bukan migrasi ini, melainkan penjaga di
 * `UserPermissionFormTest`: setiap izin yang disebut kode wajib benar-benar
 * ada sesudah penyemaian.
 *
 * Aman diulang dan aman di basis data yang sudah memilikinya.
 */
return new class extends Migration
{
    private const IZIN = [
        ['pay_purchase_materials', 'PO Material', 'Pay down payment on PO materials'],
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
        // Sengaja tidak menghapus apa pun -- izin yang sudah terlanjur
        // dilekatkan ke pengguna akan ikut terlepas, dan yang hilang bukan
        // barisnya melainkan hak akses orangnya.
    }
};
