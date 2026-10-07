<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509, langkah 1: dua kolom baru di master material.
 *
 * 1. `content_per_unit` -- "Isi per satuan". Material DIBELI per satuan beli
 *    (plastik 1 Box @ Rp 1.000.000, isi 1.000 pcs) tetapi BOM dan bahan
 *    terbuang dihitung per satuan PAKAI (pcs). PO, GR, dan stok tetap dalam
 *    satuan beli; kolom ini hanya dipakai untuk MENILAI: harga per satuan
 *    pakai = harga beli per satuan beli / content_per_unit. Bilangan bulat
 *    >= 1, bawaan 1 (satuan beli sama dengan satuan pakai). Keputusan Owner,
 *    7 Oktober 2026.
 *
 * 2. `is_drylog` -- menandai material mana yang dicatat sebagai DRYLOG di
 *    halaman pemakaian boning/repack. Lewat data, bukan id yang ditulis di
 *    kode: id berbeda antara lokal dan hosting. Bawaan false; tidak ada
 *    material yang ditandai otomatis -- Owner yang menyalakannya.
 *
 * Material yang sudah ada otomatis berisi 1 dan false, jadi tidak ada yang
 * berubah perilakunya sampai kolom ini dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedInteger('content_per_unit')->default(1)->after('material_unit_id');
            $table->boolean('is_drylog')->default(false)->after('show_in_stock');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['content_per_unit', 'is_drylog']);
        });
    }
};
