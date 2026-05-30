<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cattle_purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('cattle_category_id')->constrained('cattle_categories');
            $table->integer('qty_head');
            $table->decimal('price_per_kg', 12, 2);
            $table->string('note')->nullable(); // Note per baris sapi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattle_purchase_order_items');
    }
};
