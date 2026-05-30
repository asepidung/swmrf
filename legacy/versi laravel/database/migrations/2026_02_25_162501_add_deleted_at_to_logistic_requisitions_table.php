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
        Schema::table('logistic_requisitions', function (Blueprint $table) {
            // Menambahkan kolom deleted_at untuk fitur Soft Deletes
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logistic_requisitions', function (Blueprint $table) {
            // Menghapus kolom deleted_at saat rollback
            $table->dropSoftDeletes();
        });
    }
};
