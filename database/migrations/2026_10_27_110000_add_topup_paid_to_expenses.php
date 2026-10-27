<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kasbon yang terpakai LEBIH dari uang muka: kekurangannya diganti sebagian atau bertahap, jadi total uang
 * pengganti yang sudah diberikan dicatat (cermin `returned_amount` untuk arah sebaliknya). Selama belum
 * lunas, kita berutang ke pemohon dan dokumennya tetap Open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('topup_paid_amount', 15, 2)->default(0)->after('returned_amount');
        });

        // Settle lama langsung membayar seluruh kekurangan.
        DB::table('expenses')
            ->where('type', 'advance')
            ->whereColumn('receipt_amount', '>', 'advance_amount')
            ->update(['topup_paid_amount' => DB::raw('receipt_amount - advance_amount')]);
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('topup_paid_amount');
        });
    }
};
