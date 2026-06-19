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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('delivery_order_receipt_id')->nullable()->constrained('delivery_order_receipts')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->integer('term_of_payment')->default(0);
            $table->string('status'); // e.g. 'Belum TF', 'Sudah TF', '-', 'Lunas'
            $table->date('invoice_exchange_date')->nullable();
            $table->string('po_number')->nullable();
            $table->string('delivery_order_number')->nullable();
            $table->text('note')->nullable();
            $table->decimal('total_weight', 12, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('charge', 15, 2)->default(0);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('box')->default(0);
            $table->decimal('weight', 12, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_rp', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivables');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
