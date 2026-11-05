<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alasan peninjau meminta hitung ulang opname material (Owner, 5 November 2026). Diisi saat "Tolak / Hitung Ulang",
 * dibaca penghitung di halaman input dan kartu tugas dashboard, dikosongkan lagi saat opname dikirim ulang untuk ditinjau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_takes', function (Blueprint $table) {
            $table->text('recount_note')->nullable()->after('summary_note');
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_takes', function (Blueprint $table) {
            $table->dropColumn('recount_note');
        });
    }
};
