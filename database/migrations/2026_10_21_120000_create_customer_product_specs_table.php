<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spek customer per produk (#579, konsep disepakati Owner 20 Oktober 2026).
 *
 * `customer_product_specs` adalah spek TETAP satu pasangan customer + produk:
 * gramasi, bentuk potong, kondisi, kemasan, catatan. Semua kolom spek boleh
 * kosong; yang wajib hanya pasangannya, dan pasangan itu unik di database.
 *
 * `sales_order_items.spec` menyimpan SALINAN spek pada saat baris SO dibuat.
 * Itu sebabnya SO lama tidak ikut berubah ketika spek tetap diubah kemudian.
 * Kolomnya nullable supaya SO yang dibuat tanpa spek -- termasuk dokumen
 * yang diimpor -- tidak terganggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('weight_per_pack')->nullable();
            $table->string('cut')->nullable();
            $table->string('condition')->nullable();
            $table->string('packaging')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->json('spec')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('spec');
        });

        Schema::dropIfExists('customer_product_specs');
    }
};
