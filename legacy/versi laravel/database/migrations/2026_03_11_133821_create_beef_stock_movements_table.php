<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beef_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('condition', 50);

            $table->string('barcode', 50)->nullable();

            $table->string('transaction_type', 50); // IN_BONING, OUT_SALES, dll
            $table->string('reference_document', 255)->nullable();

            $table->decimal('weight_in', 10, 2)->default(0);
            $table->decimal('weight_out', 10, 2)->default(0);
            $table->integer('pcs_in')->default(0);
            $table->integer('pcs_out')->default(0);

            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beef_stock_movements');
    }
};
