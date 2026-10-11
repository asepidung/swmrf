<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HPP tahap 2 (issue #522): SATU buku nilai per barcode.
 *
 * Barcode berpindah tabel sepanjang hidupnya (beef_stocks -> tally_items ->
 * kembali lewat retur, relabel melahirkan barcode baru, repack mengonsumsi dan
 * melahirkan). Nilainya tidak ditempel di tabel-tabel itu, tetapi di sini.
 *
 * `cost_per_kg` NULLABLE: NULL = belum bernilai (keputusan Owner 1: barcode
 * boning boleh belum bernilai sampai costing-nya dikunci). Berbeda dengan 0 --
 * NULL tidak pernah dihitung diam-diam sebagai nol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_costs', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 50)->unique();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('cost_per_kg', 14, 2)->nullable();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_barcode', 50)->nullable()->index();
            $table->timestamp('valued_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'cost_per_kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_costs');
    }
};
