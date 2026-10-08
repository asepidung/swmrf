<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509, langkah 3: drylog wajib dan snapshot pemakaian BOM saat dikunci.
 *
 * 1. `drylog_material_id` + `drylog_qty` di `bonings` dan `repacks`. Drylog
 *    adalah satu-satunya bahan yang jumlahnya terlalu dinamis untuk dihitung
 *    BOM, jadi diisi manual per produksi. NULL berarti BELUM diisi, 0 berarti
 *    "diisi, dan hasilnya nol" -- keduanya berbeda, dan hanya NULL yang
 *    menahan Lock (keputusan Owner, 8 Oktober 2026). Materialnya dipilih dari
 *    master material, bukan id yang ditulis di kode.
 *
 * 2. `production_bom_snapshots`: hasil hitung BOM yang dibekukan saat dokumen
 *    dikunci, supaya angkanya tidak berubah bila BOM produk diedit kemudian.
 *    Sebelum dikunci, halaman produksi selalu menghitung ulang dari label
 *    terkini. Dihapus lagi saat dokumen di-unlock.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bonings', 'repacks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('drylog_material_id')->nullable()->constrained('materials')->restrictOnDelete();
                $table->unsignedInteger('drylog_qty')->nullable();
            });
        }

        Schema::create('production_bom_snapshots', function (Blueprint $table) {
            $table->id();
            $table->morphs('snapshotable');
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['snapshotable_type', 'snapshotable_id', 'material_id'], 'production_bom_snapshots_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_bom_snapshots');

        foreach (['bonings', 'repacks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('drylog_material_id');
                $table->dropColumn('drylog_qty');
            });
        }
    }
};
