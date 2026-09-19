<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Expense (issue #453): pengeluaran kas kecil, dua jenis --
 * `advance` (kasbon, Open -> Settled) dan `reimburse` (langsung Settled).
 * Tanpa approval; kontrolnya izin + jejak (LogsActivity) + daftar kasbon
 * belum kembali yang selalu terlihat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->string('type');
            $table->foreignId('bank_account_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->string('recipient_name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('advance_amount', 15, 2)->nullable();
            $table->decimal('receipt_amount', 15, 2)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Open');
            $table->text('description')->nullable();
            $table->string('receipt_photo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
