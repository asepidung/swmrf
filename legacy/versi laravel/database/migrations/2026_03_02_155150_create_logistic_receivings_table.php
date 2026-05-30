<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_number')->unique();
            $table->foreignId('logistic_purchase_order_id')->constrained('logistic_purchase_orders');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->date('receive_date');
            $table->string('sj_number')->nullable()->comment('Nomor Surat Jalan');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_receivings');
    }
};
