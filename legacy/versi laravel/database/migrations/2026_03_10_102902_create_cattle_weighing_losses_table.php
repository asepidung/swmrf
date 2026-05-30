<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_weighing_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cattle_weighing_id')->constrained('cattle_weighings')->cascadeOnDelete();

            $table->string('loss_number')->unique();
            $table->date('loss_date');
            $table->decimal('total_receive_weight', 15, 2)->default(0);
            $table->decimal('total_actual_weight', 15, 2)->default(0);
            $table->decimal('total_loss_weight', 15, 2)->default(0);
            $table->decimal('total_loss_cost', 15, 2)->default(0);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattle_weighing_losses');
    }
};
