<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Costing baru muncul di daftar Costings setelah DISIMPAN (Owner, 15 Oktober 2026): sebelum itu ia
 * hanya hasil "Create Costing" dari Draft Costing. Costing yang sudah ada dianggap sudah disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costings', function (Blueprint $table) {
            $table->timestamp('saved_at')->nullable()->after('locked_at');
        });

        DB::table('costings')->update(['saved_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('costings', function (Blueprint $table) {
            $table->dropColumn('saved_at');
        });
    }
};
