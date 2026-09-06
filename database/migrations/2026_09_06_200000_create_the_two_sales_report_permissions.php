<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk dua laporan penjualan yang baru.
 *
 * Lewat MIGRASI, bukan hanya seeder. `DatabaseSeeder` mengatur ulang kata
 * sandi superuser sehingga tidak boleh dijalankan di sistem yang berjalan --
 * jadi izin yang hanya lahir di seeder tidak akan pernah sampai ke server,
 * dan menunya tidak akan pernah muncul untuk siapa pun kecuali programmer.
 * Alasan lengkapnya di migrasi 5 September.
 *
 * Aman diulang dan aman di basis data yang sudah memilikinya.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_sales_report', 'Reports', 'View sales report'],
        ['view_fast_moving_products', 'Reports', 'View fast moving products'],
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
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke
        // pengguna akan ikut terlepas, dan yang hilang bukan barisnya
        // melainkan hak akses orangnya.
    }
};
