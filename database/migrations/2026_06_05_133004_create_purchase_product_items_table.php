<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_product_id')->constrained('purchase_products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty', 15, 2);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_product_items');
    }
};
