<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            // Hapus foreign key dan kolom lama
            $table->dropForeign(['logistic_purchase_order_id']);
            $table->dropColumn('logistic_purchase_order_id');

            // Tambah kolom morphs (ini bakal bikin kolom payable_id dan payable_type)
            // Kita taruh setelah ID agar rapi
            $table->morphs('payable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            //
        });
    }
};
