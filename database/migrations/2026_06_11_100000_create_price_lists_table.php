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
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke customer_groups tanpa constraint unique
            // 1 grup hanya punya 1 pricelist sesuai instruksi
            $table->foreignId('customer_group_id')
                  ->unique()
                  ->constrained('customer_groups')
                  ->cascadeOnDelete();
                  
            // Mencatat pembuat data
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
