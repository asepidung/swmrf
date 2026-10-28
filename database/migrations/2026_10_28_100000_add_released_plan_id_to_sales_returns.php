<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retur yang dihapus (soft delete) tetap memegang `sales_return_plan_id` yang UNIQUE, sehingga plan yang sama tidak
 * bisa ditarik ulang menjadi retur baru -- error saat hapus lalu buat ulang (temuan pelatihan 28 Oktober 2026).
 * Retur terhapus kini melepas plan-nya; id plan asal disimpan di `released_plan_id` supaya pemulihan retur
 * (restore) bisa memasangnya kembali kalau plan itu masih bebas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('released_plan_id')->nullable()->after('sales_return_plan_id');
        });

        DB::table('sales_returns')
            ->whereNotNull('deleted_at')
            ->whereNotNull('sales_return_plan_id')
            ->update([
                'released_plan_id' => DB::raw('sales_return_plan_id'),
                'sales_return_plan_id' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn('released_plan_id');
        });
    }
};
