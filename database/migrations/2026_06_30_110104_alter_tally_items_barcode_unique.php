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
        Schema::table('tally_items', function (Blueprint $table) {
            $table->dropUnique('tally_items_barcode_unique');
            $table->unique(['tally_id', 'barcode'], 'tally_items_tally_id_barcode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tally_items', function (Blueprint $table) {
            $table->dropUnique('tally_items_tally_id_barcode_unique');
            $table->unique('barcode', 'tally_items_barcode_unique');
        });
    }
};
