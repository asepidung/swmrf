<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kerugian menyimpan BERAPA BANYAK yang hilang, bukan hanya berapa rupiahnya.
 *
 * Susut kirim di halaman Approve DO sudah dicatat sejak lama, tetapi
 * rupiahnya DIPAKU NOL dan kilogramnya hanya ada di dalam kalimat:
 *
 *     'amount' => 0.00,
 *     'note'   => 'Susut Kirim DO: SWM-DO#26xxxx sebesar 12,50 Kg'
 *
 * Akibatnya laporan kerugian menampilkan Rp 0 untuk tiap susut kirim, dan
 * angka kilogramnya tidak bisa dijumlah, disaring, atau diurut -- hanya bisa
 * dibaca satu per satu.
 *
 * Project Owner, 4 September 2026: *"sebenarnya do receipt itu kalo ada
 * selisih harusnya dihitung di finansial lost"*.
 *
 * Menilainya dengan HARGA JUAL akan melebih-lebihkan: perusahaan tidak
 * kehilangan sebesar harga jual, melainkan sebesar modalnya ditambah margin
 * yang tidak jadi didapat. Angka yang benar HPP, dan HPP menunggu B.O.M.
 *
 * Jadi yang dikerjakan sekarang memindahkan kuantitasnya dari dalam kalimat
 * ke kolom sungguhan. Rupiahnya tetap nol sampai HPP ada -- ini TIDAK membuat
 * uangnya benar, ia membuat datanya siap. Saat HPP tiba, rupiahnya tinggal
 * kuantitas x HPP dari kolom yang sama, tanpa perlu menggali ulang ratusan
 * catatan lama.
 *
 * Cattle Weighing memakai kolom yang sama. Ia sudah menghitung rupiahnya
 * (berat susut x harga beli) tetapi membuang berat susutnya begitu saja --
 * dua modul kerugian di satu aplikasi yang menyimpan hal berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_losses', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->nullable()->after('amount');
            $table->string('unit', 20)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('financial_losses', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit']);
        });
    }
};
