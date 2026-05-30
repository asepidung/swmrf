<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistic_purchase_orders', function (Blueprint $table) {
            // Kita set 4 status sesuai desain lu, default-nya 'OPEN'
            $table->string('status', 20)->default('OPEN')->after('total_amount')
                ->comment('OPEN, PARTIAL, COMPLETED, CANCELLED');
        });
    }

    public function down(): void
    {
        Schema::table('logistic_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
