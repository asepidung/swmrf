<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grup yang anggotanya orang berbeda-beda (KARYAWAN, WARGA +62) membayar piutang SATUAN per customer, bukan sebagai satu grup (temuan Owner, 23 Oktober 2026).
 *
 * `pays_per_customer` = halaman Terima Pembayaran meminta customer lebih dulu, hanya menampilkan invoice customer itu, dan tidak memakai deposit bersama grup
 * (kelebihan bayar satu orang tidak boleh melunasi tagihan orang lain). Diisi true untuk dua grup itu menurut namanya; grup lain bisa diubah di master grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->boolean('pays_per_customer')->default(false)->after('is_general_price_reference');
        });

        DB::table('customer_groups')->whereIn(DB::raw('UPPER(name)'), ['KARYAWAN', 'WARGA +62'])->update(['pays_per_customer' => true]);
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn('pays_per_customer');
        });
    }
};
