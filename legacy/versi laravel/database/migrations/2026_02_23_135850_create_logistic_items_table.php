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
        Schema::create('logistic_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_category_id')->constrained();

            // GANTI 'unit' (string) JADI 'unit_id' (foreignId)
            $table->foreignId('unit_id')->constrained();

            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->boolean('show_in_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistic_items');
    }
};
