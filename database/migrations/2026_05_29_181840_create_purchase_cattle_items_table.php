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
        Schema::create('purchase_cattle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_cattle_id')->constrained('purchase_cattles')->cascadeOnDelete();
            $table->foreignId('cattle_class_id')->constrained('cattle_classes');
            $table->integer('qty');
            $table->decimal('price', 15, 2)->default(0);
            $table->text('item_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_cattle_items');
    }
};
