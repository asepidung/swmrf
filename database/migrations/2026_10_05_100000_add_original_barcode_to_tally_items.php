<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barcode ASAL sebuah barang tally yang direlabel (issue #497).
 *
 * Relabel mengganti barcode (tanggal POD ada di dalamnya), dan barcode lama
 * dulu hanya tersisa sebagai teks di catatan movement. Kolom ini
 * menyimpannya terstruktur -- yang PERTAMA, tidak ditimpa relabel kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_items', function (Blueprint $table) {
            $table->string('original_barcode', 50)->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('tally_items', function (Blueprint $table) {
            $table->dropColumn('original_barcode');
        });
    }
};
