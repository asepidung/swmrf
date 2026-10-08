<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509: drylog / pad absorber adalah SATU material yang pasti, jadi
 * halaman produksi cukup mengisi jumlahnya (Owner, 8 Oktober 2026). Pilihan
 * materialnya (`drylog_material_id`) dibuang; `drylog_qty` tetap.
 *
 * Kolom ini sempat dibuat di migrasi sebelumnya. Dilepas lewat migrasi
 * tersendiri, bukan dengan menyunting yang lama, supaya jumlah drylog yang
 * sudah terisi di basis data mana pun tidak ikut hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bonings', 'repacks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('drylog_material_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['bonings', 'repacks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('drylog_material_id')->nullable()->constrained('materials')->restrictOnDelete();
            });
        }
    }
};
