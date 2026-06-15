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
        Schema::create('delivery_plans', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke tabel pelanggan
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
                
            $table->date('delivery_date');
            
            $table->string('driver')->nullable();
            $table->string('armada')->nullable();
            $table->time('load_time')->nullable();
            
            // Pencatatan pembuat rencana
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Satu customer hanya boleh memiliki satu rencana kirim per tanggal pengiriman
            $table->unique(['customer_id', 'delivery_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_plans');
    }
};
