<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk modul Expense, issue #453. Dibuat lewat migrasi, bukan
 * seeder -- seeder tidak boleh dijalankan di server (mengatur ulang kata
 * sandi superuser). Belum dilekatkan ke siapa pun; dicatat di
 * `tertunda.md` §G.
 *
 * `view_deleted_expenses` SENGAJA belum ada di sini -- baru ada kode yang
 * membacanya (TrashedFilter di Resource-nya) mulai langkah 2, dan
 * `UserPermissionFormTest::every_permission_the_code_ignores_is_hidden()`
 * menolak izin yang lahir sebelum ada yang memakainya (pelajaran yang
 * sama dari issue #451 langkah 1).
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_expenses', 'Expenses', 'View expenses'],
        ['create_expenses', 'Expenses', 'Create expenses'],
        ['edit_expenses', 'Expenses', 'Edit expenses'],
        ['delete_expenses', 'Expenses', 'Delete expenses'],
        ['manage_expense_categories', 'Expenses', 'Manage expense categories'],
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
