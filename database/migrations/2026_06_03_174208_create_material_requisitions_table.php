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
        Schema::create('material_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            // RESTRICT, bukan cascade.
            //
            // Dulu `cascadeOnDelete()`, yang berarti menghapus satu pengguna
            // ikut menghapus dokumen ini. Definisinya diperbaiki di sini juga
            // -- bukan hanya lewat migrasi perbaikan -- supaya instalasi baru
            // tidak membangun ulang bahaya yang sama lalu menambalnya sendiri
            // beberapa langkah kemudian.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('due_date');
            $table->text('note')->nullable();
            $table->text('reject_note')->nullable();
            $table->string('terms_of_payment')->nullable();
            $table->string('tax_type')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('Requested');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_requisitions');
    }
};
