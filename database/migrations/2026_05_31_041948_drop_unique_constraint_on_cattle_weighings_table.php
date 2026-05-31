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
        Schema::table('cattle_weighings', function (Blueprint $table) {
            $table->dropForeign(['cattle_receiving_id']);
            $table->dropUnique(['cattle_receiving_id']);
            $table->foreign('cattle_receiving_id')->references('id')->on('cattle_receivings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cattle_weighings', function (Blueprint $table) {
            $table->unique('cattle_receiving_id');
        });
    }
};
