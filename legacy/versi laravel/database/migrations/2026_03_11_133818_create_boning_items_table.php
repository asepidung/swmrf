<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boning_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boning_id')->constrained('bonings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('grade_id')->constrained('grades'); // KITA PAKE INI, BUKAN 'condition'
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs');
            $table->decimal('ph_level', 4, 2)->nullable();
            $table->date('pack_date');
            $table->date('exp_date')->nullable();
            $table->string('barcode')->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boning_items');
    }
};
