<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grup yang pesanannya tidak perlu diatur dalam Rencana Kirim (KARYAWAN, WARGA +62), keputusan Owner 4 November 2026.
 *
 * `skips_delivery_plan` = Sales Order customer grup ini tidak dibuatkan Rencana Kirim (delivery_plan_id tetap kosong).
 * Diisi true untuk dua grup itu menurut namanya; grup lain bisa diubah di master grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->boolean('skips_delivery_plan')->default(false)->after('pays_per_customer');
        });

        DB::table('customer_groups')->whereIn(DB::raw('UPPER(name)'), ['KARYAWAN', 'WARGA +62'])->update(['skips_delivery_plan' => true]);
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn('skips_delivery_plan');
        });
    }
};
