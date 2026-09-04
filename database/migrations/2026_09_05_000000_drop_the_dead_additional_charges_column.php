<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom yang sudah MATI tetapi masih berdiri.
 *
 * Biaya tambahan invoice dulu disimpan sebagai JSON di
 * `invoices.additional_charges`. Pada 14 Juli 2026 ia dipindah ke tabelnya
 * sendiri, `invoice_additional_charges`, beserta seluruh isinya.
 *
 * Sejak itu kolom JSON-nya tidak pernah ditulis maupun dibaca oleh satu pun
 * bagian aplikasi -- form, cetakan, dan view rekonsiliasi semuanya memakai
 * tabel barunya. Tetapi kolomnya masih ada, masih `$fillable`, dan masih
 * di-cast menjadi array.
 *
 * Itu jebakan yang diam: siapa pun yang menulis ke sana akan DIABAIKAN oleh
 * seluruh aplikasi tanpa satu pun error, dan siapa pun yang membacanya
 * mendapat angka dari Juli. Dua tempat yang mengaku menyimpan hal yang sama
 * adalah pola yang berkali-kali menggigit proyek ini -- saldo bank, saldo
 * hutang, sisa tagihan invoice.
 *
 * Diperiksa sebelum dihapus, di basis data lokal MAUPUN hosting: tidak ada
 * satu baris pun yang masih menyimpan isi di kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'additional_charges')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('additional_charges');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'additional_charges')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->json('additional_charges')->nullable()->after('charge');
            });
        }
    }
};
