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
        Schema::create('cattle_weighing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cattle_weighing_id')->constrained('cattle_weighings')->cascadeOnDelete();
            $table->foreignId('cattle_receiving_item_id')->constrained('cattle_receiving_items')->restrictOnDelete();
            $table->decimal('actual_weight', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cattle_weighing_items');
    }
};
