<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan QC LAHIR SENDIRI sebagai tugas, bukan form kosong yang harus dicari.
 *
 * Keputusan Owner, 7 September 2026: "emang harusnya enggak ada create, kan
 * dia sifatnya seperti draft atau tugas yang muncul otomatis ketika modul
 * pasangannya dibuat".
 *
 * Bentuk sebelumnya menuntut QC membuka halaman buat sambil menyebutkan
 * dokumen mana yang didampingi lewat alamat. Itu benar secara teknis dan
 * salah secara pekerjaan: yang menulis laporan tidak sedang memilih dokumen,
 * ia sedang MENGERJAKAN TUGAS yang sudah menunggu.
 *
 * Karena barisnya kini lahir kosong, dua kolom yang dulu wajib menjadi boleh
 * kosong -- dan itu bukan pelonggaran, melainkan pemisahan keadaan:
 *
 *  - `occurred_at` kosong berarti belum ada yang menuliskan kapan hal itu
 *    terjadi;
 *  - `note` kosong berarti laporannya belum diisi sama sekali.
 *
 * `submitted_at` yang menyatakan laporannya SUDAH dikerjakan. Sengaja kolom
 * tersendiri, bukan disimpulkan dari `note` yang terisi: aturan tentang
 * bagian mana yang wajib bisa berubah kapan saja, sedangkan "sudah dikerjakan
 * atau belum" adalah kenyataan yang tidak boleh ikut berubah maknanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_reports', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable()->change();
            $table->text('note')->nullable()->change();

            $table->dateTime('submitted_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('qc_reports', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });

        // `occurred_at` dan `note` sengaja dibiarkan boleh kosong -- baris
        // yang lahir sebagai tugas memang belum punya keduanya, dan
        // mengembalikannya menjadi wajib akan gagal di baris itu.
    }
};
