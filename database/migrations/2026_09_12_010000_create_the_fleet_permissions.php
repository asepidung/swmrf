<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk Driver dan Vehicle (cluster Fleet).
 *
 * Kedelapan izin ini sudah ada di `DatabaseSeeder`, tetapi seeder itu TIDAK
 * BOLEH dijalankan di server -- ia mengatur ulang kata sandi superuser. Izin
 * yang hanya lahir di seeder karena itu tidak akan pernah sampai ke hosting:
 * policy-nya menolak semua orang, dan centangnya tidak bisa diberikan karena
 * barisnya memang tidak ada.
 *
 * Ditemukan saat peninjauan 13 September 2026, sebelum di-commit. Aturannya
 * sudah tertulis sejak 5 September: izin baru lahir lewat MIGRASI.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_drivers', 'Drivers', 'View drivers'],
        ['create_drivers', 'Drivers', 'Create drivers'],
        ['edit_drivers', 'Drivers', 'Edit drivers'],
        ['delete_drivers', 'Drivers', 'Delete drivers'],
        ['view_vehicles', 'Vehicles', 'View vehicles'],
        ['create_vehicles', 'Vehicles', 'Create vehicles'],
        ['edit_vehicles', 'Vehicles', 'Edit vehicles'],
        ['delete_vehicles', 'Vehicles', 'Delete vehicles'],
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
