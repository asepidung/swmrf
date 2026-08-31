<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo tidak lagi disimpan di master data.
 *
 * Keputusan Project Owner: saldo uang harus diturunkan dari mutasinya, persis
 * seperti stok barang yang berkumpul di tabelnya sendiri dan bukan menempel
 * sebagai kolom di master produk.
 *
 * Alasan teknisnya sama kuatnya: kolom ini di-increment/decrement tiap ada
 * pembayaran, sehingga ada DUA angka yang sama-sama mengaku benar -- kolomnya
 * dan jumlah baris di `bank_transactions`. Selama keduanya cocok tidak ada
 * yang terasa; begitu berbeda, tidak ada cara menentukan mana yang salah
 * tanpa memeriksa satu per satu. Menghapus kolomnya menghapus pertanyaan itu.
 *
 * Aman dilakukan: `bank_accounts` tabel sistem baru, tidak dipakai sistem
 * lama, dan nilainya saat ini memang persis sama dengan jumlah mutasinya
 * (diverifikasi di server: selisih nol di ketiga rekening).
 *
 * Tidak ada data yang hilang. Saldo tetap bisa dihitung kapan saja lewat
 * `BankAccount::currentBalance()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bank_accounts', 'balance')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->dropColumn('balance');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bank_accounts', 'balance')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->decimal('balance', 15, 2)->default(0);
            });
        }
    }
};
