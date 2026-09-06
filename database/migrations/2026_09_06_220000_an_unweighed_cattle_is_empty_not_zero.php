<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sapi yang belum ditimbang dicatat KOSONG, bukan nol.
 *
 * Keputusan Owner, 6 September 2026: timbang ulang wajib -- tanpa itu tidak
 * ada draft carcass -- tetapi di lapangan kadang diberi kelonggaran ketika
 * sedang sangat repot. "Dokumen timbang ulang boleh di-create tapi data
 * timbangnya dikosongkan semua; jika itu dilakukan tidak ada perhitungan ke
 * financial lost atau weight lost, dan ini risiko jalan tengah."
 *
 * Dipertegas di percakapan yang sama: kelonggarannya berlaku "kalo SEMUA sapi
 * gak ada actual weight". Sebagian kosong bukan kelonggaran, itu kelupaan --
 * dan keduanya harus tetap bisa dibedakan.
 *
 * **Sebelum ini kelonggaran itu justru menghasilkan kebalikannya.** Form
 * mengisi setiap baris dengan `actual_weight = 0`, dan hitungan susut membaca
 * 0 < berat terima untuk SETIAP ekor -- sehingga dokumen yang "dikosongkan"
 * tercatat sebagai kerugian sebesar seluruh bobot satu batch. Tanpa galat,
 * tanpa gejala.
 *
 * Kolomnya `NOT NULL`, jadi sistem tidak punya cara membedakan "belum
 * ditimbang" dari "ditimbang, hasilnya nol". Dua keadaan yang sangat berbeda,
 * persis seperti `physical_qty` di opname material.
 *
 * Tidak ada kolom penanda tambahan. Keadaan "dilewati" DIBACA dari datanya
 * sendiri -- seluruh barisnya kosong -- karena penanda tersendiri berarti dua
 * sumber untuk satu kebenaran, dan dua sumber selalu berakhir berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cattle_weighing_items', function (Blueprint $table) {
            $table->decimal('actual_weight', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan.
        //
        // Menjadikannya NOT NULL lagi akan gagal begitu ada satu dokumen yang
        // memang dilewati penimbangannya -- dan dokumen seperti itulah yang
        // membuat migrasi ini ada.
    }
};
