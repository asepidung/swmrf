<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan invoice pindah dari RETURNYA ke KARTONNYA.
 *
 * #234 menaruh `invoice_id` di baris retur. Itu mengandaikan satu retur =
 * satu pengiriman = satu invoice. Project Owner, 4 September 2026, yang
 * menunjukkan andaian itu salah: pelanggan sebesar Lion Superindo
 * mengembalikan barang dari BEBERAPA kiriman sekaligus, dan justru untuk
 * itulah retur "Unidentified Delivery" disediakan.
 *
 * Akibat andaian itu, retur tanpa surat jalan tidak berfungsi sama sekali --
 * tidak bisa dipindai, dan tiap barisnya berharga nol tanpa satu pun gejala.
 *
 * Sekarang tiap karton tahu sendiri dari kiriman mana ia berasal dan invoice
 * mana yang menagihkannya. Barcodenya yang menjawab: barcode -> tally ->
 * surat jalan -> bukti terima -> invoice.
 *
 * `sales_returns.credit_amount` TETAP ADA, tetapi artinya bergeser: ia bukan
 * lagi potongan untuk satu invoice, melainkan JUMLAH seluruh barisnya --
 * angka untuk ditampilkan. Yang memotong tagihan sekarang barisnya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('sales_return_id')
                ->constrained('invoices')->nullOnDelete();
        });

        // Yang sudah terlanjur tercatat ikut dipindahkan, supaya tidak ada
        // retur yang kehilangan potongannya saat migrasi ini jalan.
        foreach (\App\Models\SalesReturn::whereNotNull('invoice_id')->get() as $retur) {
            \App\Models\SalesReturnItem::where('sales_return_id', $retur->getKey())
                ->update(['invoice_id' => $retur->invoice_id]);
        }

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('delivery_order_id')
                ->constrained('invoices')->nullOnDelete();
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
