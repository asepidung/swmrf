<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom min_stock ke tabel logistic_items.
     */
    public function up(): void
    {
        Schema::table('logistic_items', function (Blueprint $table) {
            $table->integer('min_stock')->default(0)->after('show_in_stock');
        });
    }

    /**
     * Menghapus kolom min_stock saat rollback.
     */
    public function down(): void
    {
        Schema::table('logistic_items', function (Blueprint $table) {
            $table->dropColumn('min_stock');
        });
    }
};
