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
            $table->string('document_no', 30)->unique();
            $table->date('repack_date');
            $table->string('note', 255)->nullable();
            $table->boolean('kunci')->default(false);
            $table->string('do_number', 30)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repacks');
    }
};
