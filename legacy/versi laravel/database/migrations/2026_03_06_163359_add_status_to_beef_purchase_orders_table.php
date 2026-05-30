<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beef_purchase_orders', function (Blueprint $table) {
            // Tambahin kolom status, default-nya 'OPEN' biar PO yang lama otomatis jadi OPEN
            $table->string('status')->default('OPEN')->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('beef_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
