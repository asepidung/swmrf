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
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('additional_charges')->nullable()->after('charge');
            $table->string('exchange_by')->nullable()->after('invoice_exchange_date');
            $table->text('exchange_note')->nullable()->after('exchange_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['additional_charges', 'exchange_by', 'exchange_note']);
        });
    }
};
