<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logistic_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistic_item_id')->constrained();
            $table->decimal('qty', 10, 2);
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistic_purchase_order_items');
    }
};
