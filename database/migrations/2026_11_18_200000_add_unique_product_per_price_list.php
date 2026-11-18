<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu produk hanya boleh tercatat sekali dalam satu price list (audit pra-rilis 14 November 2026, PL-01).
 * Form sudah menolak produk kembar, tetapi impor atau dua tab yang menyimpan bersamaan bisa menghasilkan dua baris,
 * dan pembacanya memakai `first()` tanpa urutan sehingga harga yang terpakai tidak pasti.
 *
 * Dicek 18 November 2026: tidak ada baris kembar di VPS maupun coba. Bila suatu saat ada, migrasi berhenti dengan
 * pesan jelas dan TIDAK menghapus apa pun; rapikan barisnya dulu.
 */
return new class extends Migration
{
    private const INDEX = 'price_list_items_price_list_id_product_id_unique';

    public function up(): void
    {
        if (Schema::hasIndex('price_list_items', self::INDEX)) {
            return;
        }

        $kembar = \DB::table('price_list_items')
            ->select('price_list_id', 'product_id')
            ->groupBy('price_list_id', 'product_id')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($kembar > 0) {
            throw new \RuntimeException("Ada {$kembar} pasangan price list + produk yang kembar di price_list_items. Rapikan dulu, lalu jalankan ulang migrasi.");
        }

        Schema::table('price_list_items', fn (Blueprint $t) => $t->unique(['price_list_id', 'product_id'], self::INDEX));
    }

    public function down(): void
    {
        if (Schema::hasIndex('price_list_items', self::INDEX)) {
            Schema::table('price_list_items', fn (Blueprint $t) => $t->dropUnique(self::INDEX));
        }
    }
};
