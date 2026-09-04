<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rumah untuk angka yang DIPILIH MANUSIA, bukan ditulis di kode.
 *
 * Dibuat karena ambang susut Repack butuh tempat tinggal. Project Owner:
 * "harus ada isian persentase batas wajar dari qc entah itu data hardcode
 * paten atau nilainya bisa di ubah-ubah". Yang dipilih: bisa diubah -- angka
 * yang dipaku di kode berarti setiap penyesuaian menunggu rilis, dan angka
 * pertama yang ditulis hari ini pasti tebakan karena datanya memang belum ada.
 *
 * SESI bukan tempatnya. Batas POD di Tally memakai `session()`, dan itu
 * berarti angkanya milik satu peramban, hilang saat logout, dan berbeda antar
 * orang. Untuk aturan yang mengikat seluruh perusahaan itu keliru. Batas POD
 * kelak sebaiknya pindah ke sini juga -- tidak dikerjakan sekarang supaya
 * perubahannya tetap satu urusan.
 *
 * Sengaja kecil: kunci, nilai, dan siapa yang terakhir mengubahnya. Tidak ada
 * tipe data, tidak ada kelompok, tidak ada cache. Menambahkan itu semua
 * sebelum ada yang membutuhkannya hanya membuat tabel yang lebih pintar
 * daripada keperluannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
