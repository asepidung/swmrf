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
        Schema::table('qc_reports', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable()->change();
        });

        Schema::table('qc_findings', function (Blueprint $table) {
            $table->string('affected_count')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_findings', function (Blueprint $table) {
            // Reverting back to integer might lose data, but for down() it's standard
            $table->unsignedInteger('affected_count')->nullable()->change();
        });

        Schema::table('qc_reports', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable(false)->change();
        });
    }
};
