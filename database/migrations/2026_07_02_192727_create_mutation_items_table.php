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
        Schema::create('mutation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mutation_id')->constrained('mutations')->onDelete('cascade');
            $table->string('barcode');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('grade_id')->constrained('grades');
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs');
            $table->decimal('ph_level', 5, 2)->nullable();
            $table->date('pack_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('origin')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutation_items');
    }
};
