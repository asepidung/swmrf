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
        Schema::create('purchase_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_material_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_id')->constrained()->onDelete('restrict');
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
        Schema::dropIfExists('purchase_material_items');
    }
};
