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
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->default(1)->constrained();
            $table->foreignId('grade_id')->constrained();
            $table->string('barcode');
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs')->default(1);
            $table->decimal('ph_level', 4, 2)->nullable();
            $table->date('pack_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('origin')->nullable();
            $table->boolean('is_repacked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
