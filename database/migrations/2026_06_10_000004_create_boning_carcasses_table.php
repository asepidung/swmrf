<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boning_carcasses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boning_id')->constrained('bonings')->cascadeOnDelete();
            $table->foreignId('carcass_id')->constrained('carcasses')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boning_carcasses');
    }
};
