<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509, langkah 4: bahan yang TERBUANG saat boning/repack.
 *
 * Satu baris per kejadian: bahan apa, berapa (satuan pakai, bilangan bulat),
 * dan ALASAN yang wajib (gagal vakum, karton rusak, reject, dst). Boleh ada
 * banyak baris, boleh juga tidak ada sama sekali. Pemakaian yang melebihi BOM
 * dicatat di sini, bukan dengan mengubah BOM.
 *
 * Tabel ini TIDAK memotong stok. Rupiah kerugiannya ditulis ke `financial_losses`
 * saat dokumen dikunci (lihat `HasProductionMaterialRecord`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_material_wastes', function (Blueprint $table) {
            $table->id();
            $table->morphs('wasteable');
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->string('reason', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_material_wastes');
    }
};
