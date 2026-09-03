<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * Dua izin "lihat yang terhapus" yang belum pernah ada.
 *
 * `view_deleted_sales_returns` dan `view_deleted_material_stock_takes` dipakai
 * kode tetapi tidak pernah dibuat, jadi tidak bisa diberikan kepada siapa pun
 * dan fiturnya mati diam-diam.
 *
 * Ditambahkan lewat MIGRASI, bukan seeder. `DatabaseSeeder` juga mengatur
 * ulang akun superuser dan MENYETEL ULANG PASSWORDNYA ke bawaan setiap kali
 * dijalankan; menjalankannya di server hanya untuk dua baris izin akan
 * mengunci pemiliknya sendiri dari sistem.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'view_deleted_sales_returns',
            'module_name' => 'Sales Returns',
            'description' => 'View deleted sales returns',
        ],
        [
            'name' => 'view_deleted_material_stock_takes',
            'module_name' => 'Material Stock Takes',
            'description' => 'View deleted material stock takes',
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
