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
        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('qty', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->onDelete('restrict');
            $table->string('transaction_type', 50); // GR, ISSUE, ADJUSTMENT, RETUR, dll
            $table->string('reference_document')->nullable();
            $table->decimal('qty_in', 15, 2)->default(0);
            $table->decimal('qty_out', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_stock_movements');
        Schema::dropIfExists('material_stocks');
    }
};
