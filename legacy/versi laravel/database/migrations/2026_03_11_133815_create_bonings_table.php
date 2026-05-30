<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonings', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no')->unique(); // Contoh: BNS-26001
            $table->date('boning_date');
            $table->string('status', 20)->default('OPEN'); // OPEN, DONE, LOCKED
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonings');
    }
};
