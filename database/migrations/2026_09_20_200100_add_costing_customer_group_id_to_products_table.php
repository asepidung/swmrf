<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #480 (Mesin HPP), `hpp.md` §10. Kosong berarti produk dinilai
 * memakai harga umum saat costing -- keadaan yang paling banyak, bukan
 * pengecualian (§10). `nullOnDelete()`: menghapus sebuah grup pelanggan
 * tidak boleh tertahan hanya karena sebuah produk kebetulan menunjuknya
 * sebagai acuan costing -- produk itu jatuh kembali ke harga umum,
 * bukan memblokir penghapusan grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('costing_customer_group_id')
                ->nullable()
                ->after('legacy_note')
                ->constrained('customer_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('costing_customer_group_id');
        });
    }
};
