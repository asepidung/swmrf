<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot biaya beli per kelas sapi (`hpp.md` §6): biaya beli TIDAK
 * boleh dihitung sebagai satu harga dikali berat total -- harus dijumlah
 * per kelas, karena harga bisa berbeda antar kelas DI DALAM satu PO yang
 * sama (`CPO-260112`: HEIFER 61.700, STEER 62.000).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_cattle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_id')->constrained('costings')->cascadeOnDelete();
            $table->foreignId('cattle_class_id')->constrained('cattle_classes')->restrictOnDelete();
            $table->integer('head_count')->default(0);
            $table->decimal('received_weight', 12, 2)->default(0);
            $table->decimal('price_per_kg', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_cattle');
    }
};
