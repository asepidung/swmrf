<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509: nilai rupiah pemakaian material menurut BOM (keputusan Owner,
 * 9 Oktober 2026 -- "kasih nilai uang semuanya").
 *
 * Harga per satuan pakai dan nilainya dibekukan di baris snapshot saat dokumen
 * dikunci, sama seperti bahan terbuang: harga beli yang berubah kemudian tidak
 * menggeser angka lama. NULL berarti snapshot dibuat sebelum kolom ini ada
 * (tidak ditebak ulang); 0 berarti dikunci tetapi tidak ada harga sama sekali.
 * Tidak menerbitkan Financial Loss -- pemakaian bukan kerugian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_bom_snapshots', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_bom_snapshots', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'amount']);
        });
    }
};
