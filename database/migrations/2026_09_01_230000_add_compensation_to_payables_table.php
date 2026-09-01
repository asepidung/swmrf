<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kompensasi dari pemasok, dicatat terpisah dari nilai hutangnya.
 *
 * Latar belakangnya dari lapangan: pesanan datang lengkap, tetapi kualitas
 * sapinya buruk sehingga purchasing menawar kompensasi.
 *
 * KENAPA BUKAN DENGAN MENGUBAH HARGA DI PO. Purchase Order adalah catatan
 * KESEPAKATAN. Menurunkan harganya di belakang menghapus selisih antara yang
 * disepakati dan yang akhirnya dibayar, sehingga pertanyaan "tahun ini kita
 * dapat kompensasi berapa" tidak bisa dijawab sama sekali. Hutangnya pun sudah
 * terbentuk dari harga PO; mengubahnya berarti menghitung ulang dokumen yang
 * mungkin sudah disetujui.
 *
 * Karena itu `amount` tetap utuh sebagai nilai asli, dan kompensasinya berdiri
 * sebagai kolom sendiri: `balance = amount - compensation - paid_amount`.
 *
 * ALASANNYA IKUT DICATAT, dan itu bukan sekadar keterangan -- ia menentukan
 * perlakuannya:
 *
 *  - `quality`: hanya mengurangi hutang. Kerugian susut penimbangan TIDAK
 *    ikut berubah, karena susut itu tetap terjadi dan uang ini didapat untuk
 *    hal lain. Menguranginya berarti menghitung satu pemulihan untuk dua
 *    kerugian yang berbeda.
 *  - `weight`: pemulihan atas kerugian yang SAMA, jadi kerugian susutnya ikut
 *    berkurang. Tanpa ini, kerugian perusahaan tampak lebih besar daripada
 *    kenyataannya.
 *
 * Bentuknya potongan TOTAL, bukan potongan per kilo. Keputusan Project Owner,
 * 1 September 2026: yang sebenarnya dinegosiasikan memang angka bulat, dan
 * menurunkannya menjadi harga per kilo adalah ketelitian yang dikarang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->decimal('compensation', 15, 2)->default(0)->after('amount');
            $table->string('compensation_reason')->nullable()->after('compensation');
            $table->text('compensation_note')->nullable()->after('compensation_reason');
        });

        Schema::table('financial_losses', function (Blueprint $table) {
            // Nilai kerugiannya sendiri TIDAK diubah, supaya angka aslinya
            // tetap terbaca. Yang dicatat di sini berapa yang berhasil
            // ditarik kembali dari pemasok.
            $table->decimal('recovered_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropColumn(['compensation', 'compensation_reason', 'compensation_note']);
        });

        Schema::table('financial_losses', function (Blueprint $table) {
            $table->dropColumn('recovered_amount');
        });
    }
};
