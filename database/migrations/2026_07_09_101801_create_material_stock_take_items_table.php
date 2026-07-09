<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_stock_take_id')->constrained('material_stock_takes')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('system_qty', 12, 2)->default(0);
            $table->decimal('physical_qty', 12, 2)->nullable(); // Null until user enters it
            $table->decimal('difference_qty', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_take_items');
    }
};
