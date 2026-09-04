<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan pembayaran kini punya JENIS dan boleh menunjuk SATU INVOICE.
 *
 * Sebelumnya potongan hanya keterangan bebas dan nominal, lalu larut ke dalam
 * kantong uang yang membayar. Untuk biaya admin bank itu memang benar --
 * biayanya milik transfernya, bukan milik invoice mana pun.
 *
 * Untuk klaim promo tidak. Promo hampir selalu melekat pada SATU invoice
 * tertentu: "invoice bulan lalu ada promo 500 ribu". Dengan melarutkannya,
 * total yang lunas tetap benar tetapi catatan invoice MANA yang sebenarnya
 * didiskon hilang -- dan potongannya bisa mendarat di invoice yang sama sekali
 * bukan haknya, tergantung di mana uangnya kebetulan habis.
 *
 * `invoice_id` sengaja BOLEH KOSONG. Mengosongkannya berarti "potongan ini
 * milik transfernya", dan itu tetap perlakuan yang benar untuk biaya bank.
 *
 * `type` menyiapkan jawaban untuk pertanyaan yang belum bisa dijawab sistem
 * ini sama sekali: berapa yang kita berikan sebagai promo bulan ini, dan
 * berapa yang hilang sebagai biaya bank. Sekarang baru dicatat; ke mana ia
 * dibukukan menunggu keputusan tersendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_deductions', function (Blueprint $table) {
            $table->string('type')->default('other')->after('payment_id');

            // nullOnDelete, bukan cascade: menghapus invoice tidak boleh ikut
            // menghapus catatan potongan yang uangnya sudah diterima.
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('type')
                ->constrained('invoices')
                ->nullOnDelete();
        });

        // Baris lama tidak pernah punya jenis. Menebaknya dari keterangan yang
        // ditulis bebas justru memalsukan data; 'other' menyatakan apa adanya
        // bahwa jenisnya memang tidak pernah dicatat.
        DB::table('payment_deductions')->whereNull('type')->update(['type' => 'other']);
    }

    public function down(): void
    {
        Schema::table('payment_deductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('type');
        });
    }
};
