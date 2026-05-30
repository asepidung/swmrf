<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beef_purchase_order_items', function (Blueprint $table) {
            $table->id();

            // Relasi ke PO Header
            $table->foreignId('beef_purchase_order_id')->constrained('beef_purchase_orders')->onDelete('cascade');

            // Relasi ke tabel products
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            $table->decimal('qty', 12, 2);
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beef_purchase_order_items');
    }
};
