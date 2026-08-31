<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu eartag hanya boleh ada sekali, di seluruh riwayat.
 *
 * Keputusan Project Owner: eartag tidak boleh kembar dalam satu dokumen
 * penerimaan, dan lebih baik lagi tidak boleh kembar sama sekali dengan
 * riwayat. Seekor sapi memang datang sekali; nomor telinganya adalah
 * identitasnya.
 *
 * Perhatikan bedanya dengan `barcode` pada tabel transaksional, yang di
 * proyek ini SENGAJA tanpa unique index: barang hasil potong keluar-masuk
 * berkali-kali lintas dokumen, sehingga barcode yang sama sah muncul
 * berulang. Sapi hidup tidak begitu. Jangan menyamakan keduanya.
 *
 * Validasi di form sudah ada dan tetap dipertahankan sebagai fast-path --
 * ia yang memberi pesan ramah saat operator masih mengetik. Tapi validasi
 * form tidak mengikat: dua permintaan bersamaan bisa sama-sama lolos, dan
 * penyisipan lewat seeder, import, atau tinker melewatinya sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Normalkan lebih dulu. Tanpa ini, `A001` dan `a001` akan lolos
        //    dari unique index di SQLite (case-sensitive) meski MySQL
        //    menganggapnya sama.
        DB::table('cattle_receiving_items')->update([
            'eartag' => DB::raw('UPPER(TRIM(eartag))'),
        ]);

        // 2. Menolak jalan bila ada duplikat, DENGAN MENYEBUTKANNYA.
        //
        //    Membiarkan unique index gagal sendiri akan menghasilkan error
        //    driver yang tidak menyebut eartag mana yang bentrok, di tengah
        //    deploy. Dan membuang salah satu barisnya diam-diam jauh lebih
        //    buruk: itu menghapus catatan seekor sapi yang benar-benar ada.
        $duplicates = DB::table('cattle_receiving_items')
            ->select('eartag')
            ->groupBy('eartag')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('eartag');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Tidak bisa menjadikan eartag unique: masih ada '.$duplicates->count()
                .' eartag kembar di cattle_receiving_items -- '.$duplicates->take(10)->implode(', ')
                .'. Bereskan datanya lebih dulu; migrasi ini sengaja tidak memilih sendiri mana yang dibuang.'
            );
        }

        Schema::table('cattle_receiving_items', function (Blueprint $table) {
            $table->unique('eartag');
        });
    }

    public function down(): void
    {
        Schema::table('cattle_receiving_items', function (Blueprint $table) {
            $table->dropUnique(['eartag']);
        });
    }
};
