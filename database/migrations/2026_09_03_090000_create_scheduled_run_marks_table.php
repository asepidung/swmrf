<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak "kapan terakhir pemeriksaan terjadwal berjalan", di tempat yang tidak
 * ikut terhapus.
 *
 * Sebelumnya penanda ini disimpan dengan Cache::forever. Namanya menjanjikan
 * selamanya, tetapi setiap kali kami merilis, langkah deploy menjalankan
 * `php artisan optimize:clear` -- dan di dalamnya ada `cache:clear`, yang
 * menghapus penanda itu.
 *
 * Akibatnya persis kebalikan dari maksud alat ini: Dashboard mengumumkan
 * "Never" setiap habis rilis, padahal cron-nya sehat. Alat yang dipasang
 * untuk menemukan kegagalan diam-diam malah menjadi sumber alarm palsu, dan
 * alarm palsu yang berulang mengajari orang untuk mengabaikannya. Saat cron
 * benar-benar mati nanti, tidak akan ada yang percaya lagi.
 *
 * Karena itu penandanya pindah ke basis data. Tabel ini memang kecil, tetapi
 * isinya bukan cache: ia jawaban atas pertanyaan "apakah sistem ini masih
 * memeriksa dirinya sendiri", dan jawaban itu tidak boleh hilang hanya karena
 * kami merilis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_run_marks', function (Blueprint $table) {
            // Nama perintahnya sendiri yang jadi kunci, satu baris per
            // perintah. Tidak perlu id buatan.
            $table->string('key')->primary();
            $table->timestamp('ran_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_run_marks');
    }
};
