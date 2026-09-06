<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan QC: dokumen PENDAMPING, bukan proses tersendiri.
 *
 * Keputusan Owner, 6 September 2026: "qc/qa ini sebagai pendamping harusnya
 * bukan proses bisnis tersendiri, secara umum akan sama". Bentuknya sama di
 * setiap titik -- temuan dan catatan umum -- dan ia menempel pada dokumen
 * yang sedang berjalan.
 *
 * **Karena itu SATU tabel yang menempel polimorfik, bukan satu tabel per
 * titik.** Titik yang sudah disebut Owner: pemotongan (carcass), boning,
 * penyiapan barang, retur, repack, dan opname daging. Enam tabel dengan
 * bentuk yang sama berarti enam salinan aturan yang akan diam-diam berbeda --
 * pola yang berulang di proyek ini sepanjang pekan ini: umur simpan enam
 * salinan dengan tiga jawaban, counter barcode tujuh berkas, PPN yang satu
 * sisinya menanyakan kolom yang tidak pernah ada.
 *
 * ### `occurred_at` terpisah dari `created_at`, dan itu intinya
 *
 * Owner menyebut satu persoalan mendasar: laporan yang terbit saat carcass
 * dibuat sudah TELAT (potongnya selesai), sedangkan yang terbit saat timbang
 * ulang terlalu DINI (timbang bisa sehari sebelumnya).
 *
 * Persoalan itu hilang begitu waktu KEJADIAN dipisah dari waktu INPUT. Kapan
 * formnya muncul hanya menentukan kapan QC diingatkan; yang dicatat tetap
 * kapan hal itu benar-benar terjadi. Ini persoalan yang sama dengan "tanggal
 * dokumen vs waktu input" pada stok -- di sana mahal karena harus menambal
 * dua puluh lima titik yang sudah berjalan, di sini cuma satu kolom sejak
 * hari pertama.
 *
 * ### Temuan: nol atau lebih baris
 *
 * Owner: tiga kolom, tetapi tidak semuanya wajib. Proses yang tidak
 * bermasalah TIDAK menambah baris temuan sama sekali -- yang wajib hanya
 * catatan umumnya. Kalau sebuah baris ada, keterangannya wajib: temuan tanpa
 * keterangan bukan temuan, itu derau yang tidak bisa disaring setahun lagi.
 * Berapa banyak yang terkena dan tindakan yang diambil boleh menyusul, karena
 * kadang keduanya memang belum diketahui saat menulis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_reports', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();

            // Dokumen yang didampingi. Jenisnya dibatasi daftar di
            // `QcReport::DOKUMEN`, tidak pernah diambil mentah dari URL.
            $table->morphs('reportable');

            /*
             * Kapan hal yang dilaporkan BENAR-BENAR terjadi.
             *
             * Bukan kapan barisnya dibuat. Laporan QC hampir selalu ditulis
             * sesudah kejadiannya -- QC memindahkan dari catatan manualnya --
             * dan kalau yang tersimpan hanya waktu ketik, seluruh laporannya
             * menunjuk jam yang salah.
             */
            $table->dateTime('occurred_at');

            // Catatan umum: satu-satunya bagian yang WAJIB.
            $table->text('note');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['reportable_type', 'reportable_id', 'occurred_at'], 'qc_reports_dokumen_waktu_index');
        });

        Schema::create('qc_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_report_id')->constrained('qc_reports')->cascadeOnDelete();

            // Apa yang ditemukan. Wajib -- lihat catatan di kepala berkas.
            $table->text('description');

            // Berapa banyak yang terkena, dan apa yang dikerjakan. Keduanya
            // boleh menyusul.
            $table->unsignedInteger('affected_count')->nullable();
            $table->text('action_taken')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_findings');
        Schema::dropIfExists('qc_reports');
    }
};
