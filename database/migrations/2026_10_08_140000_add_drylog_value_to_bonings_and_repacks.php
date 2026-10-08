<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509: drylog dinilai rupiah (keputusan Owner, 8 Oktober 2026 --
 * "harganya akan jadi bahan perhitungan financial loss").
 *
 * Harga per pcs dan nilai drylog dibekukan saat dokumen dikunci, sama dengan
 * bahan terbuang: harga beli yang berubah kemudian tidak menggeser angka lama.
 * NULL berarti belum dikunci; 0 berarti dikunci tetapi tidak ada harga (atau
 * material drylog belum ada di master). Belum ada baris Financial Loss untuk
 * drylog -- itu menyusul.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bonings', 'repacks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('drylog_unit_price', 15, 4)->nullable();
                $table->decimal('drylog_amount', 15, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['bonings', 'repacks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['drylog_unit_price', 'drylog_amount']);
            });
        }
    }
};
