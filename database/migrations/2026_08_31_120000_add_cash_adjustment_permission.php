<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission mencatat penyesuaian kas.
 *
 * Dikirim lewat migrasi karena `DatabaseSeeder` menyetel ulang password
 * superuser tanpa syarat, jadi ia tidak boleh dijalankan di server hidup.
 * Deploy sudah menjalankan `migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'adjust_cash_balance'],
            [
                'module_name' => 'Bank Accounts',
                'description' => 'Record cash adjustment',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'adjust_cash_balance')->delete();
    }
};
