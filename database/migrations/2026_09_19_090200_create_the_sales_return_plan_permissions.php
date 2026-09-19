<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk Plan Sales Return, modul baru issue #451.
 *
 * Dibuat lewat migrasi, bukan seeder -- seeder tidak boleh dijalankan di
 * server (mengatur ulang kata sandi superuser), jadi izin yang hanya
 * lahir di sana tidak akan pernah sampai ke hosting. Belum dilekatkan ke
 * siapa pun; dicatat di `tertunda.md` §G supaya Owner mencentangnya untuk
 * peran Sales.
 *
 * `view_deleted_sales_return_plans` SENGAJA belum ada di sini -- baru ada
 * kode yang membacanya (TrashedFilter di Resource-nya) mulai langkah 2,
 * dan `UserPermissionFormTest::every_permission_the_code_ignores_is_hidden()`
 * menolak izin yang lahir sebelum ada yang memakainya. Ditambahkan di
 * migrasi langkah 2, bersamaan dengan Resource-nya.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_sales_return_plans', 'Sales Return Plans', 'View sales return plans'],
        ['create_sales_return_plans', 'Sales Return Plans', 'Create sales return plans'],
        ['edit_sales_return_plans', 'Sales Return Plans', 'Edit sales return plans'],
        ['delete_sales_return_plans', 'Sales Return Plans', 'Delete sales return plans'],
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
