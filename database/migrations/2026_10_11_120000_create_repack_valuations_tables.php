<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HPP tahap 2, PR 3 (issue #522): valuasi repack.
 *
 * `repack_valuations`: snapshot hasil menilai sebuah repack yang dikunci --
 * biaya bahan, susut (kg dan rupiah), dan status. `valued` = semua bahan
 * bernilai dan hasilnya sudah dinilai; `pending` = ada bahan yang belum bernilai,
 * hasilnya NULL dan akan dihitung otomatis begitu semua bahannya bernilai.
 * `needs_review` = ada hasil tanpa harga acuan, jadi biaya dibagi menurut berat.
 *
 * `repack_valuation_prices`: harga acuan per produk hasil yang DIKUNCI saat
 * repack dikunci (sama seperti harga di costing: price list yang berubah
 * kemudian tidak menggeser nilai repack lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repack_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repack_id')->unique()->constrained('repacks')->cascadeOnDelete();
            $table->decimal('total_input_kg', 12, 2);
            $table->decimal('total_input_cost', 16, 2)->nullable();
            $table->decimal('shrink_kg', 12, 2)->default(0);
            $table->decimal('shrink_value', 16, 2)->nullable();
            $table->decimal('ratio_k', 14, 6)->nullable();
            $table->string('status', 10);
            $table->boolean('needs_review')->default(false);
            $table->timestamp('valued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('repack_valuation_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repack_valuation_id')->constrained('repack_valuations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('reference_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->decimal('gross_price', 14, 2)->nullable();
            $table->decimal('trading_terms_percent', 6, 2)->default(0);
            $table->decimal('net_price', 14, 2)->nullable();
            $table->string('flag', 20)->nullable();
            $table->timestamps();

            $table->unique(['repack_valuation_id', 'product_id'], 'repack_valuation_prices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repack_valuation_prices');
        Schema::dropIfExists('repack_valuations');
    }
};
