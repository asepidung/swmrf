<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #451, langkah 3: retur BARU wajib menarik plan yang sudah
 * Submitted -- ditegakkan di `SalesReturn::booted()`, bukan di sini.
 *
 * Kolom ini tetap NULLABLE karena retur LAMA (sebelum fitur plan ada)
 * memang tidak punya plan sama sekali -- data historis tidak dipaksa
 * punya sesuatu yang tidak pernah ada. `unique()` menegakkan "satu plan
 * satu retur" untuk baris yang punya plan-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignId('sales_return_plan_id')->nullable()->unique()
                ->after('delivery_order_id')
                ->constrained('sales_return_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_return_plan_id');
        });
    }
};
