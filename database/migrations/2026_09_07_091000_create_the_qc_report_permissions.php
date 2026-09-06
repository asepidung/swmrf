<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk laporan QC.
 *
 * Lewat MIGRASI, bukan hanya seeder: `DatabaseSeeder` mengatur ulang kata
 * sandi superuser sehingga tidak boleh dijalankan di sistem yang berjalan,
 * dan izin yang hanya lahir di seeder tidak akan pernah sampai ke server.
 * Alasan lengkapnya di migrasi 5 September.
 *
 * `view_deleted_qc_reports` ikut dibuat karena laporannya memakai hapus
 * lunak, dan tanpa izin itu baris terhapusnya tidak bisa dilihat siapa pun --
 * keadaan yang persis menimpa Repack sampai #301.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_qc_reports', 'QC Reports', 'View QC reports'],
        ['create_qc_reports', 'QC Reports', 'Create QC reports'],
        ['edit_qc_reports', 'QC Reports', 'Edit QC reports'],
        ['delete_qc_reports', 'QC Reports', 'Delete QC reports'],
        ['view_deleted_qc_reports', 'QC Reports', 'View deleted QC reports'],
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
