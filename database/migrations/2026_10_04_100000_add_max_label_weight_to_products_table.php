<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas berat wajar satu label per produk (#486 langkah 2).
 *
 * Kosong (`null`) berarti batas bawaan 100 kg -- bukan nol. Data legacy
 * menunjukkan produk di luar offal, kulit, dan bone tidak pernah sah
 * melewati 100 kg per label, sedangkan ketiganya lazim ratusan sampai ribuan
 * kilo. Batasnya data di master produk, bukan daftar id di kode, karena id
 * legacy tidak sama dengan id di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('max_label_weight', 8, 2)->nullable()->after('legacy_note');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('max_label_weight');
        });
    }
};
