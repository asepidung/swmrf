<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #509, langkah 1: "Isi per satuan" di master material.
 *
 * Material DIBELI per satuan beli (plastik 1 Box @ Rp 1.000.000, isi 1.000
 * pcs) tetapi BOM dan bahan terbuang dihitung per satuan PAKAI (pcs). Kolom
 * ini menjembatani keduanya HANYA untuk menilai: harga per satuan pakai =
 * harga beli per satuan beli / content_per_unit. PO, GR, dan stok tidak
 * berubah. Bilangan bulat >= 1, bawaan 1 (satuan beli sama dengan satuan
 * pakai). Keputusan Owner, 7 Oktober 2026.
 *
 * Material yang sudah ada otomatis berisi 1, jadi tidak ada yang berubah
 * perilakunya sampai kolom ini dipakai.
 *
 * Penanda "material drylog" sempat dibuat di sini lalu DIBUANG atas keputusan
 * Owner: drylog hanya salah satu material, dan halaman produksi memilihnya dari
 * daftar material.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedInteger('content_per_unit')->default(1)->after('material_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('content_per_unit');
        });
    }
};
