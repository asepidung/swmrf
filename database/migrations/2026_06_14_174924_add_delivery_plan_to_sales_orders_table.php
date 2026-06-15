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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('delivery_plan_id')
                ->nullable()
                ->constrained('delivery_plans')
                ->nullOnDelete();
                
            $table->string('delivery_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_plan_id']);
            $table->dropColumn(['delivery_plan_id', 'delivery_note']);
        });
    }
};
