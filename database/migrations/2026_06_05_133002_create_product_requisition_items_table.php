<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_requisition_id')->constrained('product_requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qty', 15, 2);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requisition_items');
    }
};
