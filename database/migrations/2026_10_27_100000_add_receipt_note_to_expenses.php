<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan nota yang diketik bebas saat lapor balik kasbon (nomor nota, toko, dst.) -- foto nota hanya
 * pilihan kedua, bukan satu-satunya cara mencatat nota (keputusan Owner 27 Oktober 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('receipt_note')->nullable()->after('receipt_photo');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_note');
        });
    }
};
