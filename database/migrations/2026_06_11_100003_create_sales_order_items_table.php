<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();

            // Relasi ke header Sales Order
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();

            // Relasi ke produk, menggunakan restrict agar produk yang sudah dipesan tidak bisa dihapus sembarangan
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Tipe integer untuk berat sesuai permintaan user
            $table->integer('weight')->default(0);

            // Kolom harga
            $table->bigInteger('price')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->string('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
