<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan material di Bill of Material dan Material Waste hanya bahan penolong (temuan Owner, 23 Oktober 2026).
 *
 * `is_auxiliary` = bahan penolong (plastik, dus, label, dan sejenisnya yang dipakai produksi). Diisi true untuk material yang SUDAH dipakai
 * di BOM supaya data lama tetap utuh; sisanya diberi tanda oleh QC/Purchasing di master Material.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('is_auxiliary')->default(false)->after('show_in_stock');
        });

        DB::table('materials')
            ->whereIn('id', DB::table('product_materials')->select('material_id'))
            ->update(['is_auxiliary' => true]);
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('is_auxiliary');
        });
    }
};
