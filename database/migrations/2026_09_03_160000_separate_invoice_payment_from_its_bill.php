<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran pelanggan berhenti ditimpakan ke kolom tagihan.
 *
 * Penerimaan piutang dulu MENIMPA `invoices.balance` dengan sisa tagihan.
 * Kolom yang semula berarti "berapa yang ditagihkan" berubah makna menjadi
 * "berapa yang masih kurang", dan jumlah aslinya tidak disimpan di mana pun.
 *
 * Sementara itu form Invoice menghitung ulang kolom yang SAMA dari barang,
 * biaya, dan uang muka -- tanpa tahu apa-apa tentang pembayaran. Cukup
 * mengubah satu angka di form dan sisa tagihan melompat kembali ke jumlah
 * penuh: pembayaran yang sudah diterima lenyap dari tagihan, sementara
 * catatan alokasinya tetap ada. Tanpa error, tanpa peringatan.
 *
 * Sekarang keduanya kolom yang berbeda, mengikuti Payable:
 *
 *     ditagihkan = subtotal + charge - down_payment   (dihitung, tidak disimpan)
 *     paid_amount = jumlah seluruh alokasi pembayaran
 *     balance     = ditagihkan - paid_amount
 *
 * Form hanya boleh menyentuh yang di atas; pembayaran hanya menyentuh yang di
 * tengah. Keduanya tidak lagi bisa saling menghapus, karena bukan kolom yang
 * sama.
 *
 * Backfill di bawah ini sekaligus MEMPERBAIKI invoice yang pembayarannya
 * sudah terlanjur hilang: paid_amount dihitung ulang dari alokasi yang memang
 * masih tersimpan utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('down_payment');
        });

        // Alokasi pembayaran adalah catatan yang tidak pernah ditimpa, jadi
        // itulah sumber kebenarannya.
        DB::statement('
            UPDATE invoices
            SET paid_amount = COALESCE((
                SELECT SUM(amount_allocated) FROM payment_allocations
                WHERE payment_allocations.invoice_id = invoices.id
            ), 0)
        ');

        // Dan sisa tagihannya diturunkan ulang, bukan dipercaya apa adanya.
        DB::statement('
            UPDATE invoices
            SET balance = CASE
                WHEN (subtotal + charge - down_payment - paid_amount) < 0 THEN 0
                ELSE (subtotal + charge - down_payment - paid_amount)
            END
        ');

        // Yang sudah lunas ditandai lunas, dan yang ternyata BELUM lunas
        // dikembalikan statusnya -- termasuk invoice yang tadinya tampak lunas
        // hanya karena balance-nya pernah tertimpa nol.
        DB::statement("
            UPDATE invoices
            SET status = 'Lunas'
            WHERE balance <= 0 AND status <> 'Lunas'
        ");

        DB::statement("
            UPDATE invoices
            SET status = 'Belum Dibayar'
            WHERE balance > 0 AND status = 'Lunas'
        ");
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
