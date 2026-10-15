<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Harga beli / kg" per produk = net x k, dibulatkan (setara "BUYING PRICE" di Excel akunting); HPP =
 * harga beli + overhead per kg (produk daging). Costing lama diisi dengan HPP-nya sampai dihitung ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_items', function (Blueprint $table) {
            $table->decimal('buying_price_per_kg', 15, 2)->default(0)->after('sales_value');
        });

        DB::table('costing_items')->update(['buying_price_per_kg' => DB::raw('hpp_per_kg')]);
    }

    public function down(): void
    {
        Schema::table('costing_items', function (Blueprint $table) {
            $table->dropColumn('buying_price_per_kg');
        });
    }
};
