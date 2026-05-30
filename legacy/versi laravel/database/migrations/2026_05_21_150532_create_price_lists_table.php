<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();

            // Memastikan 1 grup hanya memiliki 1 daftar harga.
            $table->foreignId('customer_group_id')
                ->unique()
                ->constrained('customer_groups')
                ->cascadeOnDelete();

            // Mencatat pembuat data.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
