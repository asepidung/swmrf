<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509, langkah 5: nilai rupiah tiap baris bahan terbuang disimpan di
 * barisnya sendiri saat dokumen dikunci.
 *
 * Kerugiannya sudah ditulis ke `financial_losses` saat Lock, tetapi tidak ada
 * kunci yang menghubungkan satu baris kerugian ke satu baris bahan terbuang.
 * Agar halaman dokumen, cetakan, dan laporan periode bisa menampilkan nilai per
 * baris tanpa menebak pasangannya, harga per satuan pakai dan nilainya ikut
 * disimpan di sini. NULL berarti dokumennya belum dikunci (belum final); 0
 * berarti sudah dikunci tetapi tidak ada harga sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_material_wastes', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_material_wastes', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'amount']);
        });
    }
};
