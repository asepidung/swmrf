<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel utama price_lists.
            $table->foreignId('price_list_id')
                ->constrained('price_lists')
                ->cascadeOnDelete();

            // Mencegah penghapusan produk jika masih digunakan di dalam daftar harga.
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->bigInteger('price')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
