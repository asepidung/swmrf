<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan Ayah, 15 September 2026: perusahaan non-PKP, daging dijual tanpa
 * pajak. `is_taxable` tidak pernah bisa diisi lewat form Create/Edit Customer
 * sejak awal dibuat -- satu-satunya pemakai lain, `CustomerExporter`, adalah
 * kode mati (tidak direferensikan Resource mana pun, dan melanggar aturan
 * proyek yang melarang Filament Exporter untuk Excel). Pajak tetap 0, tidak
 * ada perhitungan baru yang bergantung padanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_taxable')->default(false)->after('invoice_exchange');
        });
    }
};
