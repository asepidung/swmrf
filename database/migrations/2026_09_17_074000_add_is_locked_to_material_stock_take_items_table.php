<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_take_items', function (Blueprint $table) {
            // Baris yang sudah masuk REVIEW terkunci lewat kolom ini, bukan
            // lewat status DOKUMEN saja. "Minta Hitung Ulang" membuka kunci
            // BEBERAPA baris (dokumennya kembali IN_PROGRESS), tapi baris
            // yang tidak diminta ulang harus tetap tidak bisa diubah --
            // sesuatu yang tidak bisa direpresentasikan kalau keadaan
            // "boleh diedit" hanya digantungkan pada status dokumen.
            $table->boolean('is_locked')->default(false)->after('difference_qty');
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_take_items', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
