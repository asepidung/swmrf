<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-05 (keputusan Ayah, 18 November 2026): uang muka (DP) pelanggan dicatat sebagai pembayaran sungguhan yang masuk
 * buku kas. `sales_order_id` menandai pembayaran jenis DP; pembayaran DP TIDAK menjadi deposit grup, karena sudah
 * dipakai mengurangi invoice SO-nya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'sales_order_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('customer_group_id')->constrained('sales_orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'sales_order_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_id');
        });
    }
};
