<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk Bill of Material.
 *
 * Lewat MIGRASI, bukan hanya seeder: `DatabaseSeeder` mengatur ulang kata
 * sandi superuser sehingga tidak boleh dijalankan di sistem yang berjalan,
 * dan izin yang hanya lahir di seeder tidak akan pernah sampai ke server.
 *
 * Tidak ada `view_deleted_...` karena BOM tidak memakai hapus lunak. Baris
 * BOM adalah pernyataan tentang resep yang BERLAKU SEKARANG, bukan dokumen
 * yang pernah terjadi; baris yang dicabut memang tidak punya apa-apa untuk
 * dilihat kembali. Legacy menyimpannya sebagai `is_active = 0` dengan
 * `qty = 0` -- dan itu justru merusak riwayatnya, karena jumlah lamanya ikut
 * ditimpa nol.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_product_materials', 'Bill of Materials', 'View bill of materials'],
        ['create_product_materials', 'Bill of Materials', 'Create bill of materials'],
        ['edit_product_materials', 'Bill of Materials', 'Edit bill of materials'],
        ['delete_product_materials', 'Bill of Materials', 'Delete bill of materials'],
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
        // pengguna akan ikut terlepas.
    }
};
