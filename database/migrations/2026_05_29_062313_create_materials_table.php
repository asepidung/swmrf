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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('material_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_unit_id')->constrained()->cascadeOnDelete();
            $table->integer('min_stock')->default(0)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_stock')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
