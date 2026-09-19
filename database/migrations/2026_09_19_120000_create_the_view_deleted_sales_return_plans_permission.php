<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan langkah 1: `view_deleted_sales_return_plans` ditunda sampai ada
 * kode yang membacanya -- sekarang ada, lewat TrashedFilter di
 * SalesReturnPlanResource (langkah 2, issue #451).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'view_deleted_sales_return_plans')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'view_deleted_sales_return_plans',
            'module_name' => 'Sales Return Plans',
            'description' => 'View deleted sales return plans',
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
