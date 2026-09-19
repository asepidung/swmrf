<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master kecil untuk modul Expense (issue #453). Beberapa contoh awal
 * diseed di sini (bukan `db:seed` -- seeder tidak boleh dijalankan di
 * server, mengatur ulang kata sandi superuser).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['ATK', 'AIR MINUM', 'MATERAI', 'OBAT', 'ONGKIR DRIVER', 'PARKIR'] as $nama) {
            DB::table('expense_categories')->insert([
                'name' => $nama, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
