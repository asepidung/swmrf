<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin layar Nilai Barcode (HPP tahap 2, issue #522): melihat nilai per barcode,
 * mengisi manual barcode yang belum bernilai, dan menjalankan penilaian stok
 * awal. Dibaca `BarcodeCostPolicy`. Belum dilekatkan ke siapa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'manage_barcode_costs')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'manage_barcode_costs',
            'module_name' => 'Costings',
            'description' => 'Manage barcode costs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke pengguna ikut terlepas.
    }
};
