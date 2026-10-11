<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin laporan HPP tahap 2 (issue #522): Nilai Stok dan HPP Penjualan. Dibaca
 * `StockValueReport::canAccess()` dan `SalesCostReport::canAccess()`. Belum
 * dilekatkan ke siapa pun; dicatat di `tertunda.md` §G.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'view_cost_reports')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'view_cost_reports',
            'module_name' => 'Costings',
            'description' => 'View cost reports (stock value, sales cost)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke pengguna ikut terlepas.
    }
};
