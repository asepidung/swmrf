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
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->string('barcode', 50);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->restrictOnDelete();
            $table->decimal('weight', 8, 2)->default(0);
            $table->integer('qty_pcs')->default(0);
            $table->decimal('ph_level', 4, 2)->nullable();
            $table->date('pack_date')->nullable();
            $table->enum('status', ['MISSING', 'MATCHED', 'UNEXPECTED'])->default('MISSING');
            $table->boolean('is_manual')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
            
            $table->unique(['stock_take_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
