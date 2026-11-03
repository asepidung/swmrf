<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grup customer bisa dinonaktifkan (Owner, 3 November 2026): grup yang tak dipakai lagi (mis. REGULER, UNGROUP)
     * hilang dari pilihan di form customer tanpa dihapus, sehingga riwayat piutang dan price list-nya utuh.
     */
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('pays_per_customer');
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
