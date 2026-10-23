<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boning hasil impor legacy dikeluarkan dari Draft Costing (keputusan Owner, 23 Oktober 2026): Draft Costing memuat semua boning yang
 * belum punya costing tersimpan, jadi ratusan boning lama akan tampil sebagai "menunggu costing".
 *
 * `legacy_imported` = boning ini riwayat impor; diisi oleh `legacy:import-bonings` dan bisa dibalik per boning lewat aksi "Buka untuk costing"
 * (izin sendiri) untuk kejadian luar biasa, mis. boning 1 November yang memang perlu dicosting. Migrasi ini mengisi true untuk boning yang sudah
 * tercatat di `legacy_import_map`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonings', function (Blueprint $table) {
            $table->boolean('legacy_imported')->default(false)->after('note');
        });

        DB::table('bonings')
            ->whereIn('id', DB::table('legacy_import_map')->where('entity', 'boning')->select('new_id'))
            ->update(['legacy_imported' => true]);

        if (! DB::table('permissions')->where('name', 'reopen_boning_for_costing')->exists()) {
            DB::table('permissions')->insert(['name' => 'reopen_boning_for_costing', 'module_name' => 'Boning', 'description' => 'Reopen imported boning for costing', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('bonings', function (Blueprint $table) {
            $table->dropColumn('legacy_imported');
        });
    }
};
