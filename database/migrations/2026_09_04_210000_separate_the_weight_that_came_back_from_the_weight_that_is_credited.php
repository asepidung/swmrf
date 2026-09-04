<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berat yang MASUK GUDANG dan berat yang DIKREDITKAN adalah dua hal berbeda.
 *
 * Kita mengirim satu box seberat 20,00 kg. Pelanggan menimbang ulang dan
 * mendapat 19,80 kg -- itu yang mereka catat dan itu dasar pembayarannya,
 * jadi bukti terima disesuaikan ke 19,80 dan invoice memakai angka itu.
 * Project Owner, 4 September 2026, menyatakan ini alur yang biasa, bukan
 * penyimpangan.
 *
 * Ketika box itu dikembalikan, yang terpindai berat KIRIM kita: 20,00. Batas
 * berat yang dipasang #236 lalu menolaknya, karena 20,00 > 19,80. Sudah
 * dibuktikan dengan test sebelum kolom ini dibuat:
 *
 *     Returning 20.00 kg of SIRLOIN is more than the 19.80 kg billed
 *     on invoice SWM-INV#260001.
 *
 * Kesalahannya memaksa dua angka yang memang tidak harus sama menjadi satu.
 * Yang benar:
 *
 *     weight          berat FISIK yang masuk gudang       -> stok
 *     credited_weight berat yang pernah DITAGIHKAN        -> uang
 *
 * `origin_delivery_order_id` menjawab pertanyaan lain. Karton yang kartonnya
 * rusak dan sulit dibaca barcodenya di-barcode ULANG saat diretur, sehingga
 * barcodenya baru dan tidak menunjuk ke kiriman mana pun. Selama ini asalnya
 * diambil dari surat jalan yang tertulis di returnya -- dan retur lintas
 * pengiriman tidak menyebut surat jalan apa pun, sehingga barang semacam itu
 * berharga NOL tanpa satu pun gejala. Justru kombinasi itu yang paling
 * mungkin terjadi. Sekarang asalnya ditanyakan, bukan ditebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('credited_weight', 10, 2)->nullable()->after('weight');
            $table->foreignId('origin_delivery_order_id')->nullable()->after('invoice_id')
                ->constrained('delivery_orders')->nullOnDelete();
        });

        // Yang sudah tercatat dikreditkan sebesar berat fisiknya -- itu memang
        // yang berlaku sebelum kolom ini ada.
        \App\Models\SalesReturnItem::whereNotNull('line_amount')
            ->update(['credited_weight' => \Illuminate\Support\Facades\DB::raw('weight')]);
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_delivery_order_id');
            $table->dropColumn('credited_weight');
        });
    }
};
