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
            $table->foreignId('repack_id')->constrained('repacks')->cascadeOnDelete();
            $table->string('barcode', 30)->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->decimal('weight', 8, 2);
            $table->integer('qty_pcs')->default(0)->nullable();
            $table->decimal('ph_level', 3, 1)->nullable();
            $table->date('pack_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->string('note', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repack_results');
    }
};
