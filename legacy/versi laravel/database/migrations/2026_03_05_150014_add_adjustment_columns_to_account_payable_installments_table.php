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
        Schema::table('account_payable_installments', function (Blueprint $table) {
            // Kita selipin kolom potongan setelah amount_paid
            $table->decimal('discount_amount', 15, 2)->default(0)->after('amount_paid');
            $table->decimal('tax_deduction_amount', 15, 2)->default(0)->after('discount_amount');

            // Kolom sakti: Total hutang yang berkurang (Bayar + Diskon + Pajak)
            $table->decimal('total_debt_reduction', 15, 2)->default(0)->after('tax_deduction_amount');
        });
    }

    public function down(): void
    {
        Schema::table('account_payable_installments', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'tax_deduction_amount', 'total_debt_reduction']);
        });
    }
};
