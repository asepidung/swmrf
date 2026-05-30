<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repack_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repack_id')->constrained('repacks')->cascadeOnDelete();
            $table->string('barcode', 50)->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs')->default(0);
            $table->decimal('ph_level', 3, 1)->nullable();
            $table->date('pack_date');
            $table->date('exp_date')->nullable();
            $table->string('origin', 50)->default('BONING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repack_materials');
    }
};
