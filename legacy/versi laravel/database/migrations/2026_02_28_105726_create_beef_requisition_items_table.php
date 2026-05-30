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
        Schema::create('beef_requisition_items', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel header beef_requisitions
            $table->foreignId('beef_requisition_id')
                ->constrained('beef_requisitions')
                ->onDelete('cascade');

            // Relasi ke tabel products (id, name, dll)
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');

            $table->decimal('qty', 12, 2);
            $table->decimal('price', 15, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beef_requisition_items');
    }
};
