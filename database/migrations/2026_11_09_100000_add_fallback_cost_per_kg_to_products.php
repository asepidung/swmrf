<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga cadangan per kg tiap produk (Owner, 9 November 2026): dipakai HANYA untuk barcode yang masuk lewat retur jual atau
 * temuan opname bila produknya tidak punya stok berharga untuk dirata-ratakan. Opsional; kosong = perilaku lama (belum bernilai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('fallback_cost_per_kg', 14, 2)->nullable()->after('max_label_weight');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fallback_cost_per_kg');
        });
    }
};
