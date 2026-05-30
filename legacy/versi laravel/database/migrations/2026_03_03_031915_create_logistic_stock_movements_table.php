<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_item_id')->constrained('logistic_items');
            $table->string('transaction_type', 50)->comment('GR, ISSUE, RETUR, dll');
            $table->string('reference_document')->nullable()->comment('Nomor GR / Dokumen');
            $table->integer('qty_in')->default(0);
            $table->integer('qty_out')->default(0);
            $table->integer('balance')->comment('Sisa stok akhir saat transaksi ini terjadi');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_stock_movements');
    }
};
