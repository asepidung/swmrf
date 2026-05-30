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
        /* Membuat tabel logistic_requisition_items untuk detail barang yang diminta */
        Schema::create('logistic_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_requisition_id')->constrained('logistic_requisitions')->cascadeOnDelete();
            $table->foreignId('logistic_item_id')->constrained('logistic_items')->cascadeOnDelete();
            
            $table->decimal('qty', 10, 2);
            $table->decimal('price', 15, 2)->default(0);
            $table->string('note')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistic_requisition_items');
    }
};
