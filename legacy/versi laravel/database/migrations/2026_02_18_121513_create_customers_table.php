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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_group_id')->constrained();
            $table->foreignId('segment_id')->constrained();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->integer('top_days')->default(0); // Term of Payment

            // Dokumen yang Diperlukan (Sesuai Gambar 2 + PO)
            $table->boolean('req_invoice')->default(false);
            $table->boolean('req_joss')->default(false);
            $table->boolean('req_nkv')->default(false);
            $table->boolean('req_phd')->default(false);
            $table->boolean('req_halal')->default(false);
            $table->boolean('req_uji_lab')->default(false);
            $table->boolean('req_sv')->default(false);
            $table->boolean('req_po')->default(false); // Tambahan PO

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
