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
        Schema::create('material_findings', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->date('date');
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade');
            $table->decimal('qty', 10, 2);
            $table->text('note')->nullable();
            // RESTRICT, bukan cascade.
            //
            // Dulu `cascadeOnDelete()`, yang berarti menghapus satu pengguna
            // ikut menghapus dokumen ini. Definisinya diperbaiki di sini juga
            // -- bukan hanya lewat migrasi perbaikan -- supaya instalasi baru
            // tidak membangun ulang bahaya yang sama lalu menambalnya sendiri
            // beberapa langkah kemudian.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_findings');
    }
};
