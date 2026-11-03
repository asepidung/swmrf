<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tally yang dihapus (soft delete) tidak dipulihkan: SO-nya dibuatkan tally BARU dengan nomor berikutnya (Owner, 3
 * November 2026, "dihapus ya hilang, nomor melompat").
 *
 * Dulu `sales_order_id` unique mutlak, sehingga tally terhapus masih memegang SO-nya dan satu-satunya jalan adalah
 * memulihkan baris lama (nomornya tetap). Sekarang kuncinya hanya berlaku untuk tally yang masih hidup, lewat kolom
 * turunan yang bernilai NULL begitu tally dihapus (unique mengabaikan NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tallies', function (Blueprint $table) {
            // Indeks biasa menggantikan peran unique bagi foreign key sebelum unique-nya dilepas.
            $table->index('sales_order_id', 'tallies_sales_order_id_index');
        });

        Schema::table('tallies', function (Blueprint $table) {
            $table->dropUnique(['sales_order_id']);
        });

        Schema::table('tallies', function (Blueprint $table) {
            $table->unsignedBigInteger('active_sales_order_id')
                ->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN sales_order_id END');
            $table->unique('active_sales_order_id', 'tallies_active_sales_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tallies', function (Blueprint $table) {
            $table->dropUnique('tallies_active_sales_order_id_unique');
        });

        Schema::table('tallies', function (Blueprint $table) {
            $table->dropColumn('active_sales_order_id');
            $table->unique('sales_order_id');
        });

        Schema::table('tallies', function (Blueprint $table) {
            $table->dropIndex('tallies_sales_order_id_index');
        });
    }
};
