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
        Schema::create('carcass_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carcass_id')->constrained('carcasses')->cascadeOnDelete();
            // Nembak ke sapi spesifik di timbangan
            $table->foreignId('cattle_weighing_item_id')->constrained('cattle_weighing_items')->restrictOnDelete();

            $table->decimal('carcass_1', 8, 2)->default(0); // Belahan 1
            $table->decimal('carcass_2', 8, 2)->default(0); // Belahan 2
            $table->decimal('hides', 8, 2)->default(0);     // Kulit
            $table->decimal('tail', 8, 2)->default(0);      // Buntut
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carcass_items');
    }
};
