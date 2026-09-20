<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per produk yang muncul di boning-nya (`hpp.md` §2, §8) --
 * bukan seluruh katalog produk seperti sheet legacy yang memuat puluhan
 * baris ber-qty 0.
 *
 * `reference_group_id` adalah SALINAN `products.costing_customer_group_id`
 * pada saat costing dibuat -- disimpan lepas dari FK produknya supaya
 * tetap terbaca meski acuan produk itu berubah belakangan (`hpp.md` §6:
 * harga dikunci saat costing dibuat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_id')->constrained('costings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('weight_kg', 12, 2)->default(0);
            $table->foreignId('reference_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->decimal('gross_price', 15, 2)->default(0);
            $table->decimal('trading_terms_percent', 5, 2)->default(0);
            $table->decimal('net_price', 15, 2)->default(0);
            $table->decimal('sales_value', 15, 2)->default(0);
            $table->decimal('hpp_per_kg', 15, 2)->default(0);
            // NO_REFERENCE = produk tanpa grup acuan (dinilai harga umum,
            // costing tetap jadi); NO_PRICE = harga tidak ditemukan sama
            // sekali (nilai 0, MENOLAK Lock selama masih ada baris ini).
            $table->string('flag')->nullable();
            $table->timestamps();

            $table->unique(['costing_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_items');
    }
};
