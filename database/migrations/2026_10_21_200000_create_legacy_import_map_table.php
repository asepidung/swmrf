<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghubung id legacy -> id aplikasi baru untuk dokumen hasil impor riwayat (receive, timbang, karkas, boning, dst.).
 * Dipakai importer untuk menghubungkan dokumen anak ke induknya dan supaya impor bisa diulang tanpa dobel. Hanya ditulis lewat perintah
 * `legacy:import-*` (DB::table); aplikasi tidak membacanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_map', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 60);
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['entity', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_map');
    }
};
