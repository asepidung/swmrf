<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bill of Material: bahan penolong apa saja yang dipakai sebuah produk.
 *
 * Legacy sudah punya bentuknya di `barang/managebom.php`, tetapi bentuk itu
 * mengunci dirinya sendiri: slot bahannya ditulis di kode satu per satu
 * (karung, karton top, karton bottom, plastik, linier, drylog, tray), nomor
 * kategorinya dipatok angka, dan Top dibedakan dari Bottom lewat NAMA
 * bahannya. Ganti nama sebuah bahan, dan BOM-nya rusak tanpa satu pun gejala.
 *
 * Di sini barisnya bebas. Bahan dipilih dari master bahan, bukan dari slot
 * yang sudah disiapkan -- karena tiap produk memang punya varian berbeda,
 * dan yang berikutnya belum tentu muat di slot yang ada.
 *
 * ---
 *
 * TIGA HAL YANG SENGAJA DIPUTUSKAN BEGINI:
 *
 * 1. `quantity` BOLEH KOSONG, dan kosong bukan nol.
 *
 *    Drylog dipakai di hampir semua produk, tetapi jumlahnya berbeda-beda
 *    walau produknya sama -- keputusan Owner, 6 September 2026. Menuliskannya
 *    `0` berarti "tidak dipakai", dan itu keterangan yang salah. Kosong
 *    berarti "dipakai, jumlahnya tidak tetap"; nol tidak akan pernah berarti
 *    apa pun di sini karena baris yang tidak dipakai memang dihapus.
 *
 * 2. `basis` DISIMPAN, tidak ditulis di label.
 *
 *    Legacy menulis satuannya sebagai teks di sebelah kolom ("buah per pcs",
 *    "buah per box"), sehingga yang menghitung pemakaian harus menebak dasar
 *    hitung tiap bahan. Padahal satu produk memakai keduanya sekaligus:
 *    plastik vakum per potong daging, karton dan karung per box. Dasar hitung
 *    adalah bagian dari resepnya, bukan hiasan tampilan.
 *
 *    Dua nilainya mengikuti bentuk stok daging yang sudah ada: satu baris
 *    `beef_stocks` adalah satu BOX berbarcode, dan `qty_pcs` isinya.
 *
 * 3. Satu bahan hanya boleh muncul SEKALI per produk.
 *
 *    Dua baris bahan yang sama dengan jumlah berbeda tidak punya arti yang
 *    bisa dipertahankan -- yang membacanya harus menebak dijumlahkan atau
 *    yang belakangan menang. Legacy menahannya lewat pemeriksaan di PHP;
 *    di sini basis datanya sendiri yang menolak.
 *
 * ---
 *
 * BOM ini TIDAK menggerakkan stok. Stok material tetap dikurangi manual dalam
 * satuan kasarnya (box, ikat) setelah produksi -- keputusan Owner, karena
 * menghitung plastik satu per satu di lapangan tidak mungkin: satu dus bisa
 * 5.000 lembar, dan margin errornya jauh lebih besar daripada yang diperbaiki.
 * Yang dicatat di sini adalah KOMPOSISI, supaya cost-nya bisa dihitung saat
 * dibutuhkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Bahan sengaja TIDAK ikut terhapus. Menghapus sebuah bahan
            // seharusnya gagal selama masih ada produk yang memakainya --
            // resep yang kehilangan bahannya diam-diam lebih buruk daripada
            // penghapusan yang ditolak.
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();

            $table->unsignedInteger('quantity')->nullable();
            $table->string('basis', 10);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_materials');
    }
};
