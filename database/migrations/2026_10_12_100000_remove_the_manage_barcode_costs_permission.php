<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Layar Nilai Barcode dibatalkan (keputusan Owner, 12 Oktober 2026): nilai barcode
 * cukup datang dari costing yang dikunci, tanpa layar isian manual. Izin
 * `manage_barcode_costs` (dibuat oleh `2026_10_11_110000`) tidak dibaca kode lagi,
 * jadi dibuang; baris pivot ke pengguna ikut terhapus (cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('name', 'manage_barcode_costs')->delete();
    }

    public function down(): void
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
};
