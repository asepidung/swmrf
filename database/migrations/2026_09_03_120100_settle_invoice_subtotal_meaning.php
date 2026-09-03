<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `invoices.subtotal` dulu berarti dua hal yang berbeda.
 *
 * Nilai awalnya, saat form baru dibuka dari sebuah bukti terima, dihitung dari
 * BARANG SAJA. Tetapi begitu ada satu field disentuh, `updateTotals()`
 * menimpanya dengan barang DITAMBAH biaya tambahan.
 *
 * Jadi arti kolom itu bergantung pada apakah ada yang sempat mengetik sesuatu
 * di form -- dan tidak ada satu pun cara membedakannya dari baris yang sudah
 * tersimpan. Sementara kolom `charge`, yang sejak awal disediakan untuk biaya
 * tambahan, tidak pernah diisi sama sekali.
 *
 * Sekarang artinya dikunci:
 *
 *     subtotal = barang, sesudah diskon barisnya
 *     charge   = biaya tambahan, sesudah diskon barisnya
 *     balance  = subtotal + charge - uang muka
 *
 * Migrasi ini menghitung ulang KEDUA kolom itu dari baris-barisnya, yang
 * memang sumber kebenarannya.
 *
 * `balance` SENGAJA TIDAK DISENTUH. Itu angka yang benar-benar ditagihkan
 * kepada pelanggan, dan kedua rumus lama menghasilkan nilai yang sama
 * untuknya. Menghitungnya ulang hanya menambah risiko tanpa memperbaiki apa
 * pun -- dan kalau ada yang meleset, yang meleset itu tagihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Barang, dan biaya tambahan, masing-masing dari tabel barisnya.
        DB::statement('
            UPDATE invoices
            SET subtotal = COALESCE((
                SELECT SUM(amount) FROM invoice_items
                WHERE invoice_items.invoice_id = invoices.id
            ), 0)
        ');

        DB::statement('
            UPDATE invoices
            SET charge = COALESCE((
                SELECT SUM(amount) FROM invoice_additional_charges
                WHERE invoice_additional_charges.invoice_id = invoices.id
            ), 0)
        ');
    }

    public function down(): void
    {
        // Kembali ke arti yang lama: subtotal memuat keduanya.
        DB::statement('
            UPDATE invoices
            SET subtotal = COALESCE((
                SELECT SUM(amount) FROM invoice_items
                WHERE invoice_items.invoice_id = invoices.id
            ), 0) + COALESCE((
                SELECT SUM(amount) FROM invoice_additional_charges
                WHERE invoice_additional_charges.invoice_id = invoices.id
            ), 0)
        ');
    }
};
