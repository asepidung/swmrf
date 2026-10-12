<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan final valuasi repack (keputusan Owner, 12 Oktober 2026, issue #522):
 * ikut-produk -> susut ke Financial Loss -> produk baru / kilo kelebihan pakai
 * rata-rata stok produk itu -> selisih dibagi ke produk yang disesetan.
 * Menggantikan bagi-nilai-jual (#525).
 *
 * - `repack_valuation_prices` dibuat ulang: bukan lagi harga acuan (gross, potongan,
 *   net), melainkan SNAPSHOT nilai stok produk baru/kelebihan saat repack dikunci
 *   (`stock_cost_per_kg`; NULL = tidak ada stok bernilai, memakai rata-rata bahan +
 *   `needs_review`). Isinya hanya data uji; repack di-Lock ulang sesudahnya.
 * - `repack_valuations`: `ratio_k` (pembagi nilai jual) dibuang; ditambah nilai produk
 *   baru dan selisihnya supaya halaman dokumen bisa menampilkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('repack_valuation_prices');

        Schema::create('repack_valuation_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repack_valuation_id')->constrained('repack_valuations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('stock_cost_per_kg', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['repack_valuation_id', 'product_id'], 'repack_valuation_prices_unique');
        });

        Schema::table('repack_valuations', function (Blueprint $table) {
            $table->dropColumn('ratio_k');
        });

        Schema::table('repack_valuations', function (Blueprint $table) {
            $table->decimal('new_product_value', 16, 2)->nullable()->after('shrink_value');
            $table->decimal('difference_value', 16, 2)->nullable()->after('new_product_value');
        });
    }

    public function down(): void
    {
        Schema::table('repack_valuations', function (Blueprint $table) {
            $table->dropColumn(['new_product_value', 'difference_value']);
        });

        Schema::table('repack_valuations', function (Blueprint $table) {
            $table->decimal('ratio_k', 14, 6)->nullable();
        });

        Schema::dropIfExists('repack_valuation_prices');

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
};
