<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bikin Tabel Induk GR Beef
        Schema::create('beef_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_number')->unique();
            $table->foreignId('beef_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('receive_date');
            $table->string('sj_number')->nullable(); // Nomor Surat Jalan
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Bikin Tabel Detail Item GR Beef
        Schema::create('beef_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beef_receiving_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beef_item_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qty_received', 10, 2); // Pake decimal karena daging biasanya hitungan KG (koma)
            $table->decimal('price', 15, 2)->default(0); // Harga bawaan dari PO
            $table->decimal('subtotal', 15, 2)->default(0); // Qty * Harga
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // Drop anaknya dulu baru bapaknya biar gak kena error foreign key
        Schema::dropIfExists('beef_receiving_items');
        Schema::dropIfExists('beef_receivings');
    }
};
