<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boning_carcasses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boning_id')->constrained('bonings')->cascadeOnDelete();

            // Sesuaikan 'nama_tabel_karkas_lu' dengan tabel hasil potong sapi lu yang sebenarnya
            // Misal: $table->foreignId('slaughter_id')->constrained('cattle_slaughters');
            $table->foreignId('slaughter_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boning_carcasses');
    }
};
