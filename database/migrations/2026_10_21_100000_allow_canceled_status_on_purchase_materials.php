<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tombol "Cancel" di daftar PO material menulis status `canceled`, tetapi kolomnya hanya mengizinkan
 * pending/partial/completed (PO produk sudah punya `canceled` sejak awal). Dengan mode ketat MySQL tombol itu gagal
 * ("Data truncated"). Ditemukan saat latihan impor riwayat PO (21 Oktober 2026): PO batal dari legacy juga butuh nilai ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_materials', function (Blueprint $table) {
            $table->enum('status', ['pending', 'partial', 'completed', 'canceled'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan: baris berstatus canceled akan ikut gagal dikonversi.
    }
};
