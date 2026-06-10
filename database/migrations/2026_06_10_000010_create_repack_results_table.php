<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repack_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repack_id')->constrained('repacks')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses')->default(1); // Default to JONGGOL (1)
            $table->foreignId('grade_id')->constrained('grades');
            $table->string('barcode', 50);
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs')->default(0);
            $table->decimal('ph_level', 3, 1)->nullable();
            $table->date('pack_date');
            $table->date('exp_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repack_results');
    }
};
