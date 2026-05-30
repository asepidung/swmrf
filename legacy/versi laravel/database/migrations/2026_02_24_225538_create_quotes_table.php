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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            
            /* Menyimpan ID user yang membuat quote */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            /* Teks utama kutipan */
            $table->text('quote_text');
            
            /* Nama pembuat atau tokoh (opsional) */
            $table->string('author_name')->nullable();
            
            /* OTOMATIS membuat kolom created_at untuk tanggal pembuatan */
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
