<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kompensasi disederhanakan: ia tidak pernah menyentuh kerugian.
 *
 * Rancangan sebelumnya membedakan kompensasi karena BERAT dan karena
 * KUALITAS, dan yang karena berat ikut mengurangi kerugian susut yang
 * tercatat.
 *
 * Penjelasan Project Owner, 2 September 2026, membatalkan dasarnya:
 * komplainnya selalu soal mutu -- lemaknya terlalu banyak, hasil dagingnya
 * sedikit. Pemasok tidak pernah mengganti karena beratnya kurang; susut
 * timbang adalah urusan lain yang sudah tercatat sendiri.
 *
 * Artinya pembedaan itu MEMBEDAKAN SESUATU YANG TIDAK DIBEDAKAN DI LAPANGAN,
 * dan justru pembedaan itulah bagian yang berbahaya: salah memilih alasan
 * menghapus kerugian susut yang nyata, tanpa satu pun gejala.
 *
 * Sekarang aturannya satu kalimat: kompensasi mengurangi hutang, titik.
 *
 * Kerugian susut tetap utuh karena beratnya memang tidak pernah sampai, dan
 * kompensasi didapat karena hal lain. Gambaran utuhnya tetap terbaca --
 * rugi susut sekian, dapat kompensasi sekian -- dan siapa pun yang melihat
 * keduanya bisa menyimpulkan sendiri. Menetralkan angkanya lebih dulu justru
 * menyembunyikan dua-duanya.
 *
 * Pertanyaan "bagaimana kalau kompensasinya lebih besar daripada kerugian"
 * ikut hilang dengan sendirinya: tidak ada yang perlu dibatasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropColumn('compensation_reason');
        });

        Schema::table('financial_losses', function (Blueprint $table) {
            $table->dropColumn('recovered_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->string('compensation_reason')->nullable()->after('compensation');
        });

        Schema::table('financial_losses', function (Blueprint $table) {
            $table->decimal('recovered_amount', 15, 2)->default(0)->after('amount');
        });
    }
};
