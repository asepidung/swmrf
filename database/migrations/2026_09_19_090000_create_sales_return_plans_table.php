<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Sales Return (issue #451) -- rencana klaim yang dibuat SALES,
 * sebelum gudang menerima fisiknya lewat Sales Return yang sudah ada.
 *
 * `delivery_order_id` boleh kosong (unidentified DO); bila diisi harus
 * milik `customer_id` yang sama -- ditegakkan di model, bukan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_number')->unique();
            $table->date('plan_date');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('Draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_plans');
    }
};
