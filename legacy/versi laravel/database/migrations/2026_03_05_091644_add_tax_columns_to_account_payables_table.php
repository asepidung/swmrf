<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            // Kita selipin DPP dan Tax persis sebelum total_amount biar rapi
            $table->decimal('dpp_amount', 15, 2)->default(0)->after('supplier_id');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('dpp_amount');
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropColumn(['dpp_amount', 'tax_amount']);
        });
    }
};
