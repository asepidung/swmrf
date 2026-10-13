<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda data: produk ini tidak ikut kg dasar overhead costing (#541) -- hanya "Offal borong" dan
 * Kulit/Hides. Kategori OFFAL tidak bisa jadi penanda (Liver, Sweetbread dll. ikut kg overhead).
 * Nama hanya PENGISI AWAL di sini; sesudahnya yang dibaca kalkulator adalah kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('excluded_from_overhead')->default(false)->after('is_active');
        });

        DB::table('products')
            ->whereRaw("UPPER(TRIM(name)) IN ('OFFAL', 'KULIT', 'HIDES')")
            ->update(['excluded_from_overhead' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('excluded_from_overhead');
        });
    }
};
