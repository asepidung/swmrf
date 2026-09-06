<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empat kolom jejak audit boleh kosong, supaya tidak ada lagi yang mengarang.
 *
 * Tiga belas tempat menulis `auth()->id() ?? 1` -- "kalau tidak ada yang
 * login, tulis pengguna id 1". Angka itu bukan pilihan yang dipikirkan;
 * ia sekadar angka pertama.
 *
 * Hari ini pengguna id 1 TIDAK ADA. Yang paling awal id 100, dan itu
 * permintaan Owner justru supaya id 1 dan seterusnya bisa dipakai pengguna
 * warisan waktu data aplikasi lama dipindahkan nanti. Jadi sekarang fallback
 * itu menunjuk orang yang tidak ada: kolom ber-foreign-key menolaknya dengan
 * galat SQL, yang tanpa foreign key menyimpan angka nyangkut.
 *
 * **Yang membuatnya mendesak adalah apa yang terjadi SESUDAH data lama
 * masuk.** Begitu id 1 menjadi orang sungguhan, kegagalan yang tadinya keras
 * berubah menjadi diam: tindakan tercatat rapi atas nama orang yang tidak
 * mengerjakannya, tanpa satu pun gejala. Jejak audit yang salah dan tidak
 * kelihatan salah lebih buruk daripada tidak ada jejak sama sekali.
 *
 * Dari sembilan kolom yang ditulis tiga belas tempat itu, lima sudah boleh
 * kosong. Empat ini belum, dan justru empat inilah yang memaksa fallback itu
 * ada.
 *
 * "Tidak diketahui" adalah jawaban yang jujur. "Pak Asep" bukan.
 */
return new class extends Migration
{
    private const KOLOM = [
        ['material_findings', 'created_by'],
        ['purchase_materials', 'approved_by'],
        ['beef_stock_movements', 'created_by'],
        ['material_stock_movements', 'created_by'],
    ];

    public function up(): void
    {
        foreach (self::KOLOM as [$tabel, $kolom]) {
            Schema::table($tabel, function (Blueprint $table) use ($kolom) {
                $table->unsignedBigInteger($kolom)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan.
        //
        // Mengembalikannya menjadi NOT NULL akan gagal begitu ada satu baris
        // yang memang tidak diketahui pembuatnya -- dan baris seperti itu
        // justru yang membuat migrasi ini ada.
    }
};
