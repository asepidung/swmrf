<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #471 langkah 2: `legacy:import-master` memindahkan `barang` ->
 * `products`, dan Hafizh eksplisit meminta `kodeinduk`/`karton`/`drylog`/
 * `plastik` "dicatat ke catatan produk" -- petunjuk BOM lama per produk
 * (bukan sesuatu yang cocok dipetakan ke `structure_type`/`parent_id`
 * swmrf tanpa keputusan Owner tersendiri). `Product` tidak punya kolom
 * catatan bebas sama sekali sebelum ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('legacy_note')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('legacy_note');
        });
    }
};
