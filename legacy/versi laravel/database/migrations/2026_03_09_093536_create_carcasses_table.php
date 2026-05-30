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
        Schema::create('carcasses', function (Blueprint $table) {
            $table->id();
            $table->string('carcass_no')->unique(); // Contoh: CRS-26001
            $table->foreignId('cattle_weighing_id')->constrained('cattle_weighings')->restrictOnDelete();
            $table->date('kill_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carcasses');
    }
};
