<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan langkah 1: `lock_costings` ditunda sampai ada kode yang
 * membacanya -- sekarang ada, lewat aksi Lock/Unlock di `EditCosting`/
 * `ViewCosting` (langkah 2, issue #480).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'lock_costings')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'lock_costings',
            'module_name' => 'Costings',
            'description' => 'Lock/unlock costings',
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
