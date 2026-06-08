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
        Schema::create('goods_receipt_materials', function (Blueprint $table) {
            $table->id();
            $table->string('gr_number')->unique();
            $table->foreignId('purchase_material_id')->constrained()->onDelete('restrict');
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->date('receive_date');
            $table->string('sj_number')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('goods_receipt_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_material_id')->constrained('goods_receipt_materials')->onDelete('cascade');
            $table->foreignId('material_id')->constrained()->onDelete('restrict');
            $table->integer('qty_received');
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
        Schema::dropIfExists('goods_receipt_material_items');
        Schema::dropIfExists('goods_receipt_materials');
    }
};
