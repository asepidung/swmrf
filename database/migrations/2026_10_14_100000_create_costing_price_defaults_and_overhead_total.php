<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #541 -- harga costing disalin dari costing sebelumnya, bukan price list.
 *
 * `costing_price_defaults` = harga patokan per produk (gross + net), diisi sekali dari sheet
 * costing terbaru lalu diperbarui setiap kali sebuah costing dikunci. `costings.overhead_total`
 * = overhead per kg x kg daging (tanpa offal/kulit) yang ikut dibagi ke HPP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_price_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->decimal('gross_price', 15, 2)->default(0);
            $table->decimal('net_price', 15, 2)->default(0);
            // 'import' (sheet awal) atau 'costing' (diperbarui saat costing dikunci).
            $table->string('source', 20)->default('costing');
            $table->timestamps();
        });

        Schema::table('costings', function (Blueprint $table) {
            $table->decimal('overhead_total', 15, 2)->default(0)->after('overhead_per_kg');
        });
    }

    public function down(): void
    {
        Schema::table('costings', function (Blueprint $table) {
            $table->dropColumn('overhead_total');
        });

        Schema::dropIfExists('costing_price_defaults');
    }
};
