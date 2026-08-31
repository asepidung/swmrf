<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon bawaan per pelanggan.
 *
 * Sebelum ini, diskon 2% untuk Distribution Center Lion Superindo ditentukan
 * dengan mencocokkan POTONGAN NAMA pelanggan (`DCA`, `DCB`, `DCC`) di dalam
 * kode InvoiceResource. Aturannya benar secara bisnis, tempatnya yang keliru:
 * mengganti nama pelanggan diam-diam mengubah harganya, dan pelanggan baru
 * yang namanya kebetulan memuat huruf itu ikut mendapat diskon.
 *
 * Letaknya di `customers`, bukan `customer_groups`. Grup LION berisi 29
 * pelanggan -- tiga DC, sisanya toko dan kantor -- sehingga diskon di tingkat
 * grup akan mengenai 26 toko yang tidak berhak. Bukan pula di
 * `customer_segments`, yang berlaku lintas perusahaan sehingga DC milik
 * pelanggan lain ikut kena.
 *
 * Kolom ini hanya mengisi NILAI AWAL diskon di Sales Order. Yang tersimpan di
 * SO tetap yang menentukan, jadi kalau diskon DC suatu saat berubah, SO lama
 * tetap memakai angka yang berlaku saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Persen dengan dua desimal: cukup untuk 2, 2,5, maupun 12,75.
            $table->decimal('default_discount', 5, 2)->default(0)->after('top');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('default_discount');
        });
    }
};
