<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_id')->constrained('tallies')->cascadeOnDelete();
            $table->string('barcode', 50)->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('grade_id')->constrained('grades');
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs')->default(0);
            $table->decimal('ph_level', 3, 1)->nullable();
            $table->date('pack_date');
            $table->date('exp_date')->nullable();
            $table->string('origin', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_items');
    }
};
