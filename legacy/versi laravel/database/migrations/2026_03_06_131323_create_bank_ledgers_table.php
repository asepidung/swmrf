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
        Schema::create('bank_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_bank_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->decimal('debit', 15, 2)->default(0);  // Uang Masuk
            $table->decimal('credit', 15, 2)->default(0); // Uang Keluar
            $table->decimal('balance_after', 15, 2);      // Saldo Akhir saat itu
            $table->string('description')->nullable();

            // Polimorfik: Biar bisa nyambung ke Cicilan AP, atau nanti ke Sales
            $table->nullableMorphs('referenceable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_ledgers');
    }
};
