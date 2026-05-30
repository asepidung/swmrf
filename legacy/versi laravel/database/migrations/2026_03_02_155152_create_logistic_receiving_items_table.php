<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_receiving_id')->constrained('logistic_receivings')->cascadeOnDelete();
            $table->foreignId('logistic_item_id')->constrained('logistic_items');

            /* Dibuat integer karena barang logistik hitungannya bulat (Pcs, Pack, Dus, dll) */
            $table->integer('qty_received');

            /* Harga dan Subtotal tetap decimal karena untuk hitungan uang/Rupiah */
            $table->decimal('price', 15, 2)->comment('Harga satuan saat PO');
            $table->decimal('subtotal', 15, 2)->comment('qty_received * price');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_receiving_items');
    }
};
