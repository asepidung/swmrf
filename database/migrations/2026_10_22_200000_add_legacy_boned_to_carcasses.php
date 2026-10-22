<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Karkas hasil impor legacy dikeluarkan dari daftar karkas yang menunggu boning (keputusan Owner, 22 Oktober 2026).
 *
 * Boning legacy tidak punya hubungan ke karkas, jadi tanpa penanda ini seluruh karkas hasil impor tampil sebagai
 * "belum di-boning". `legacy_boned` = karkas ini dianggap sudah di-boning (riwayat impor); diisi oleh `legacy:import-carcasses`
 * dan bisa dibalik per karkas lewat aksi "Buka untuk boning" (izin sendiri) bila ada kejadian luar biasa.
 * Migrasi ini mengisi true untuk karkas yang sudah tercatat di `legacy_import_map`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carcasses', function (Blueprint $table) {
            $table->boolean('legacy_boned')->default(false)->after('note');
        });

        DB::table('carcasses')
            ->whereIn('id', DB::table('legacy_import_map')->where('entity', 'carcass')->select('new_id'))
            ->update(['legacy_boned' => true]);

        if (! DB::table('permissions')->where('name', 'reopen_carcass_for_boning')->exists()) {
            DB::table('permissions')->insert(['name' => 'reopen_carcass_for_boning', 'module_name' => 'Carcass', 'description' => 'Reopen imported carcass for boning', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('carcasses', function (Blueprint $table) {
            $table->dropColumn('legacy_boned');
        });
    }
};
