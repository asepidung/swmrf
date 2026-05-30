<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_banks', function (Blueprint $table) {
            // Kita taruh setelah ID biar posisinya di depan
            $table->string('initial')->after('id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_banks', function (Blueprint $table) {
            $table->dropColumn('initial');
        });
    }
};
