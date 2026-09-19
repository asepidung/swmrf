<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan langkah 1: `view_deleted_expenses` ditunda sampai ada kode yang
 * membacanya -- sekarang ada, lewat TrashedFilter di ExpenseResource
 * (langkah 2, issue #453).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'view_deleted_expenses')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'view_deleted_expenses',
            'module_name' => 'Expenses',
            'description' => 'View deleted expenses',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke
        // pengguna akan ikut terlepas.
    }
};
