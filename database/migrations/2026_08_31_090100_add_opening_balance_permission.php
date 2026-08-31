<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission menyetel saldo awal, dikirim lewat migrasi.
 *
 * Alasannya sama dengan `view_cash_book`: `DatabaseSeeder` menyetel ulang
 * password superuser tanpa syarat, jadi ia tidak boleh dijalankan di server
 * yang sudah hidup. Deploy menjalankan `migrate --force`, jadi ini jalur yang
 * aman. Permission-nya tetap didaftarkan di seeder untuk lingkungan baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'set_opening_balance'],
            [
                'module_name' => 'Bank Accounts',
                'description' => 'Set opening balance',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'set_opening_balance')->delete();
    }
};
