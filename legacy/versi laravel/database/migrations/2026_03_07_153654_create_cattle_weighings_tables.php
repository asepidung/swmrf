<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TABEL HEADER (Weighing)
        Schema::create('cattle_weighings', function (Blueprint $table) {
            $table->id();

            // Relasi ke GRC (Penerimaan)
            $table->foreignId('cattle_receiving_id')
                ->constrained('cattle_receivings')
                ->restrictOnDelete(); // Gak boleh hapus GRC kalau udah ditimbang

            $table->string('weigh_no')->unique(); // WGH-26001
            $table->date('weigh_date');
            $table->text('note')->nullable();

            // Siapa yang nimbang
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });

        // 2. TABEL DETAIL (Weighing Items)
        Schema::create('cattle_weighing_items', function (Blueprint $table) {
            $table->id();

            // Relasi ke Header Timbangan
            $table->foreignId('cattle_weighing_id')
                ->constrained('cattle_weighings')
                ->cascadeOnDelete(); // Kalau header dihapus, detail ikut terhapus

            // Relasi ke Item GRC (KUNCI NORMALISASI: Eartag & Berat Awal ada di sini)
            $table->foreignId('cattle_receiving_item_id')
                ->constrained('cattle_receiving_items')
                ->restrictOnDelete();

            $table->decimal('weight', 10, 2); // Berat Aktual saat ditimbang
            $table->text('notes')->nullable(); // Catatan per sapi (opsional)

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // Drop tabel detail dulu biar nggak kena error foreign key
        Schema::dropIfExists('cattle_weighing_items');
        Schema::dropIfExists('cattle_weighings');
    }
};
