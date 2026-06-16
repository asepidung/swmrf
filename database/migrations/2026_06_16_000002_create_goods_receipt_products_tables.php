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
        Schema::create('goods_receipt_products', function (Blueprint $table) {
            $table->id();
            $table->string('gr_number')->unique();
            $table->foreignId('purchase_product_id')->constrained('purchase_products')->onDelete('restrict');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->date('receive_date');
            $table->string('sj_number')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('goods_receipt_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_product_id')->constrained('goods_receipt_products')->onDelete('cascade');
            $table->string('barcode', 50); // standard 25-char barcode or supplier barcode
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('grade_id')->constrained('grades')->onDelete('restrict');
            $table->decimal('weight', 10, 2);
            $table->integer('qty_pcs')->default(0);
            $table->decimal('ph_level', 3, 1)->nullable();
            $table->date('pack_date');
            $table->string('origin', 50);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_product_items');
        Schema::dropIfExists('goods_receipt_products');
    }
};
