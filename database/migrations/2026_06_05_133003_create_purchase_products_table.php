<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_requisition_id')->constrained('product_requisitions')->cascadeOnDelete();
            $table->string('po_number')->unique();
            $table->date('po_date');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_products');
    }
};
