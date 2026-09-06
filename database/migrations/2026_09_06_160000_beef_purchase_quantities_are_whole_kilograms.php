<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qty pembelian daging disimpan BULAT, sama seperti yang ditampilkan.
 *
 * Kolomnya `decimal(15,2)` dan kotak isiannya menerima koma, tetapi layar
 * daftar rinci maupun berkas cetaknya sama-sama membulatkan. Jadi 12,50 bisa
 * tersimpan 12,50 dan terbaca 13 di dua tempat sekaligus -- tanpa galat, dan
 * tanpa satu pun cara melihat angka yang sebenarnya selain membuka basis
 * datanya.
 *
 * Keputusan Owner, 6 September 2026: daging dibeli dalam kilogram BULAT,
 * sama seperti material -- "material itu gak ada qty koma-komaan", 5
 * September. Yang salah karena itu tipe kolomnya, bukan tampilannya.
 *
 * Angka berkomanya diperiksa lebih dulu di basis data lokal MAUPUN server
 * sebelum migrasi ini ditulis: empat baris permintaan dan empat baris PO,
 * tidak satu pun berkoma. Jadi tidak ada angka yang dibulatkan diam-diam di
 * sini.
 *
 * Berat yang sesungguhnya -- yang memang berkoma -- tetap dicatat di tempat
 * yang benar: `goods_receipt_product_items.weight` saat barangnya diterima
 * dan ditimbang. Yang dibulatkan di sini PESANANNYA, bukan timbangannya.
 */
return new class extends Migration
{
    private const KOLOM = [
        'product_requisition_items',
        'purchase_product_items',
    ];

    public function up(): void
    {
        foreach (self::KOLOM as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->unsignedInteger('qty')->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::KOLOM as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->decimal('qty', 15, 2)->default(0)->change();
            });
        }
    }
};
