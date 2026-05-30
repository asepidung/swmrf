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
        /* Membuat tabel logistic_requisitions untuk data utama permintaan */
        Schema::create('logistic_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            /* Kolom supplier bisa nullable karena kadang requester belum tahu beli di mana */
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            
            $table->date('due_date');
            $table->text('note')->nullable();
            $table->string('terms_of_payment')->nullable();
            
            /* Status pajak dan nominal */
            $table->enum('tax_type', ['No', '11', '12'])->default('No');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            /* Menyimpan status dokumen berdasarkan alur persetujuan */
            $table->string('status')->default('Requested');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistic_requisitions');
    }
};
