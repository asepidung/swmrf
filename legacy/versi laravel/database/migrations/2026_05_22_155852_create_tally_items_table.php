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
        Schema::create('tally_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_sheet_id')->constrained('tally_sheets')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('actual_weight', 10, 2);
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_items');
    }
};
