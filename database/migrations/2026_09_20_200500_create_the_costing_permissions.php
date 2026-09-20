<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk modul Costing (Mesin HPP), issue #480. Dibuat lewat migrasi,
 * bukan seeder. Belum dilekatkan ke siapa pun; dicatat di `tertunda.md` §A.
 *
 * `view_deleted_costings` dan `lock_costings` SENGAJA belum ada di sini
 * -- baru ada kode yang membacanya (TrashedFilter dan aksi Lock/Unlock
 * di Resource-nya) mulai langkah 2/3, pola yang sama dengan
 * `view_deleted_sales_return_plans`/`view_deleted_expenses`.
 * `UserPermissionFormTest::every_permission_the_code_ignores_is_hidden()`
 * menolak izin yang lahir sebelum ada yang memakainya.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_costings', 'Costings', 'View costings'],
        ['create_costings', 'Costings', 'Create costings'],
        ['edit_costings', 'Costings', 'Edit costings'],
        ['delete_costings', 'Costings', 'Delete costings'],
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
