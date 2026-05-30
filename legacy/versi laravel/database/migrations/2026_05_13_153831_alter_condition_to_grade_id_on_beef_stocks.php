<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* Menghapus kolom condition dan menambahkan grade_id pada tabel beef_stocks */
        Schema::table('beef_stocks', function (Blueprint $table) {
            $table->dropColumn('condition');
            $table->unsignedBigInteger('grade_id')->after('warehouse_id')->default(1);
            $table->foreign('grade_id')->references('id')->on('grades')->onDelete('restrict');
        });

        /* Menghapus kolom condition dan menambahkan grade_id pada tabel beef_stock_movements */
        Schema::table('beef_stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('beef_stock_movements', 'condition')) {
                $table->dropColumn('condition');
                $table->unsignedBigInteger('grade_id')->after('warehouse_id')->default(1);
                $table->foreign('grade_id')->references('id')->on('grades')->onDelete('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beef_stocks', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropColumn('grade_id');
            $table->string('condition', 50)->default('CHILL');
        });

        Schema::table('beef_stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('beef_stock_movements', 'grade_id')) {
                $table->dropForeign(['grade_id']);
                $table->dropColumn('grade_id');
                $table->string('condition', 50)->default('CHILL');
            }
        });
    }
};
