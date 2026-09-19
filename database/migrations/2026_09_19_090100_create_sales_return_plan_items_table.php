<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item klaim di dalam Plan Sales Return (issue #451).
 *
 * Cuma `product_id` + berat/qty yang DIKLAIM pelanggan -- tidak ada
 * warehouse_id/grade_id/barcode di sini, karena plan ini dokumen sales
 * (belum ada barang fisik yang dipegang). Barang fisiknya dicatat di
 * `sales_return_items` yang sudah ada begitu gudang menarik plan ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('sales_return_plans')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('claimed_weight', 10, 2);
            $table->integer('claimed_qty_pcs')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_plan_items');
    }
};
