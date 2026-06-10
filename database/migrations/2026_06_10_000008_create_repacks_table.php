<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repacks', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 50)->unique();
            $table->date('repack_date');
            $table->string('status', 50)->default('OPEN');
            $table->boolean('kunci')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repacks');
    }
};
