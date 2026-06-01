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
        Schema::create('cattle_weighings', function (Blueprint $table) {
            $table->id();
            $table->string('weighing_number')->unique();
            $table->foreignId('cattle_receiving_id')->unique()->constrained('cattle_receivings')->restrictOnDelete();
            $table->date('weighing_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cattle_weighings');
    }
};
