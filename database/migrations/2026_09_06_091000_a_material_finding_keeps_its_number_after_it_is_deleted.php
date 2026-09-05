<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan material dihapus LUNAK, supaya nomornya tidak dipakai ulang.
 *
 * Menghapus satu temuan mengembalikan stoknya, dan penghapusan itu menulis
 * catatan pergerakan yang MENYEBUT nomor dokumennya sebagai acuan. Selama
 * barisnya benar-benar hilang, nomor itu bebas dan dipakai lagi oleh temuan
 * berikutnya -- sehingga dua dokumen yang berbeda dirujuk oleh satu nomor
 * yang sama di buku besar, dan tidak ada cara membedakannya lagi.
 *
 * Sesudah ini barisnya tetap ada, hanya ditandai terhapus, dan penomorannya
 * menghitung yang terhapus lewat `withTrashed()` -- aturan yang sama dengan
 * enam belas model dokumen lain di aplikasi ini, dijaga
 * `StockGuardsTest::test_every_soft_deleting_document_counts_its_deleted_numbers`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('material_findings', 'deleted_at')) {
            return;
        }

        Schema::table('material_findings', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('material_findings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
