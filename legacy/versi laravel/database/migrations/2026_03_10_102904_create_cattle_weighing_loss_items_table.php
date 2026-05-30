<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_weighing_loss_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cattle_weighing_loss_id')->constrained('cattle_weighing_losses')->cascadeOnDelete();

            // Mengacu ke id baris timbangan sapi (cattle_weighing_items)
            $table->foreignId('cattle_weighing_item_id')->constrained('cattle_weighing_items')->cascadeOnDelete();
            $table->foreignId('cattle_category_id')->constrained('cattle_categories');

            $table->string('eartag');
            $table->decimal('receive_weight', 10, 2)->default(0);
            $table->decimal('actual_weight', 10, 2)->default(0);
            $table->decimal('loss_weight', 10, 2)->default(0);
            $table->decimal('price_per_kg', 15, 2)->default(0);
            $table->decimal('loss_cost', 15, 2)->default(0);
            $table->string('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattle_weighing_loss_items');
    }
};
