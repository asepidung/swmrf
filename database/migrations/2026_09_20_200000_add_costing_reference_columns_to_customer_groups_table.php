<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #480 (Mesin HPP). `hpp.md` §9/§11.3: hanya LION dan HYPERMART yang
 * boleh jadi acuan penilaian costing -- grup transaksional lain (KARYAWAN,
 * WARGA, dst, yang akan terisi dari migrasi legacy) TIDAK BOLEH, karena
 * memasukkan harga karyawan/warga sebagai acuan menaikkan HPP SELURUH
 * produk lain (rasio `k` dibagi oleh total nilai jual gabungan).
 *
 * `is_general_price_reference`: `hpp.md` §10 -- "kosong = harga umum" butuh
 * SATU sumber harga yang jelas, bukan tebakan. `price_lists.customer_group_id`
 * tetap NOT NULL (tidak diubah) -- daripada mengizinkan price list tanpa
 * grup, satu grup nyata ditandai sebagai representasi "harga umum" (Owner,
 * lewat Hafizh, sudah menyebut nama grup ini akan bernama "UMUM" saat impor
 * legacy -- lihat catatan customer_group #471). Bukan tebakan implementor:
 * kosongnya `costing_customer_group_id` di `products` HARUS jatuh ke grup
 * yang secara eksplisit ditandai begini, bukan grup mana pun yang kebetulan
 * `is_costing_reference=true`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->decimal('trading_terms_percent', 5, 2)->default(0)->after('top');
            $table->boolean('is_costing_reference')->default(false)->after('trading_terms_percent');
            $table->boolean('is_general_price_reference')->default(false)->after('is_costing_reference');
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn(['trading_terms_percent', 'is_costing_reference', 'is_general_price_reference']);
        });
    }
};
