<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retur penjualan akhirnya menyentuh uang.
 *
 * Sampai 4 September 2026 menyetujui retur mengembalikan barangnya ke stok
 * tetapi tidak mengurangi tagihan pelanggan sama sekali: barang senilai tiga
 * juta kembali, tagihannya tetap penuh.
 *
 * Empat kolom, dan tiga di antaranya SNAPSHOT.
 *
 * `unit_price` dan `line_amount` merekam harga jual pada saat barang itu
 * dikirim, bukan harga hari ini. Harga berubah -- lewat Price List, lewat
 * diskon pelanggan, lewat Sales Order berikutnya -- dan nota retur yang ikut
 * berubah berarti angka yang sudah disepakati bergerak sendiri di belakang
 * punggung orang.
 *
 * `credit_amount` disimpan, bukan dihitung dari barisnya, karena alasan yang
 * sama: ia adalah nilai nota retur pada saat disetujui. Barisnya boleh saja
 * kelak dihapus atau diperbaiki; nilai yang sudah memotong tagihan tidak
 * boleh ikut bergerak.
 *
 * `invoice_id` boleh kosong. Retur bisa terjadi SEBELUM invoicenya dibuat,
 * dan ketika itu ia menunggu -- menempel sendiri saat invoice untuk surat
 * jalan itu lahir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('delivery_order_id')
                ->constrained('invoices')->nullOnDelete();
            $table->decimal('credit_amount', 15, 2)->default(0)->after('invoice_id');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->nullable()->after('qty_pcs');
            $table->decimal('line_amount', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_amount']);
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('credit_amount');
        });
    }
};
