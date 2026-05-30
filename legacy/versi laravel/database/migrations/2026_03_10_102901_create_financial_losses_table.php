<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_losses', function (Blueprint $table) {
            $table->id();

            // Ini magic-nya Polymorphic, otomatis bikin kolom lossable_type & lossable_id
            $table->morphs('lossable');

            $table->string('reference_number');
            $table->date('loss_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 50)->default('POSTED');
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_losses');
    }
};
