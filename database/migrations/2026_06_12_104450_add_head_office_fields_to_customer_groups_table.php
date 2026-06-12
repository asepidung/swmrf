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
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->text('head_office_address')->nullable()->after('name');
            $table->string('head_office_pic')->nullable()->after('head_office_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn(['head_office_address', 'head_office_pic']);
        });
    }
};
