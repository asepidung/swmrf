<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->integer('qty_pcs')->default(0);
            $table->decimal('ph_level', 3, 1)->nullable();
            $table->date('pack_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('origin', 50)->default('BONING');
        });
    }

    public function down(): void
    {
        Schema::table('tally_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'qty_pcs', 'ph_level', 'pack_date', 'exp_date', 'origin']);
        });
    }
};
