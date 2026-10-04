<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor batch barang (issue #497): nomor dokumen induk produksi yang
 * sebenarnya -- Boning (`doc_no`), Repack (`doc_no`), penerimaan produk
 * (`gr_number`), retur (`return_number`). Kosong = tanpa batch (barang
 * legacy, temuan, atau induknya tidak diketahui).
 *
 * KOLOM, bukan segmen barcode (keputusan Owner 5 Oktober 2026): barcode
 * tetap 28 digit, batch ikut barangnya ke mana pun ia berpindah tabel.
 */
return new class extends Migration
{
    private const TABLES = [
        'boning_items',
        'repack_results',
        'goods_receipt_product_items',
        'sales_return_items',
        'stock_take_items',
        'tally_items',
        'beef_stocks',
        'mutation_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('batch_no', 40)->nullable()->after('barcode');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('batch_no');
            });
        }
    }
};
