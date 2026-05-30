<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            // Nyambungnya ke PO, karena 1 PO = 1 Hutang
            $table->foreignId('logistic_purchase_order_id')->constrained('logistic_purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');

            // Total tagihan, pembayaran, dan sisa
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);

            $table->string('status', 20)->default('UNPAID')->comment('UNPAID, PARTIAL, PAID');
            $table->date('due_date')->nullable();
            $table->string('invoice_number')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
