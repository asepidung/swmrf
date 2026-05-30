<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_item_id')->constrained('logistic_items')->cascadeOnDelete();
            $table->integer('qty')->default(0); // Ini angka stok real-time nya
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_stocks');
    }
};
