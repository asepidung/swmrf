<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_lists', function (Blueprint $table) {
            // Menghapus foreign key constraint
            $table->dropForeign(['customer_group_id']);

            // Menghapus index unique
            $table->dropUnique(['customer_group_id']);

            // Membuat ulang foreign key constraint tanpa unique
            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_lists', function (Blueprint $table) {
            // Menghapus foreign key constraint
            $table->dropForeign(['customer_group_id']);

            // Menambahkan kembali index unique
            $table->unique('customer_group_id');

            // Membuat ulang foreign key constraint
            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();
        });
    }
};
