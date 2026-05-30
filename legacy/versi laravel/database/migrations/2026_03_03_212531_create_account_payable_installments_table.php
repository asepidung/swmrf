<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payable_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_payable_id')->constrained('account_payables')->cascadeOnDelete();

            $table->date('payment_date');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('proof_of_payment')->nullable(); // Untuk path file gambar/pdf
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payable_installments');
    }
};
