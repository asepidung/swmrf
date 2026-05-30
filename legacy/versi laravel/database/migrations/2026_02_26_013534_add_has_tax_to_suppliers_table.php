<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom has_tax setelah kolom term_of_payment
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('has_tax')->default(false)->after('term_of_payment');
        });
    }

    /**
     * Menghapus kolom has_tax saat proses rollback
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('has_tax');
        });
    }
};
