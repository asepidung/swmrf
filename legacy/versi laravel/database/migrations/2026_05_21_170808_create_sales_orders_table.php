<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();

            // Nomor SO akan di-generate otomatis, dibuat unique agar tidak ada duplikasi
            $table->string('so_number')->unique();

            // Relasi ke tabel pelanggan
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->date('delivery_date');
            $table->string('po_number')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('status')->default('waiting');
            $table->text('note')->nullable();

            // Pencatatan pengguna yang membuat dokumen
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
        Schema::dropIfExists('sales_orders');
    }
};
