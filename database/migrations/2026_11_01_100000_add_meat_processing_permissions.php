<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * Menu Meat Processing (stok produk kategori Processing, dipisah dari Beef Stock; Owner, 1 November 2026).
 *
 * Izinnya sendiri dan TIDAK disalin dari izin Beef Stock: siapa pun yang boleh melihat Beef Stock tidak otomatis boleh
 * melihat menu ini. Owner memberikannya per peran.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['name' => 'view_meat_processing_stocks', 'module_name' => 'Meat Processing Stocks', 'description' => 'View meat processing stocks'],
        ['name' => 'view_meat_processing_movements', 'module_name' => 'Meat Processing Movements', 'description' => 'View meat processing movements'],
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
