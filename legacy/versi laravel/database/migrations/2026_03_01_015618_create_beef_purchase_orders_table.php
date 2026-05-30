<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beef_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();

            // Relasi ke dokumen request asalnya
            $table->foreignId('beef_requisition_id')->constrained('beef_requisitions')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');

            // Siapa Finance yang approve
            $table->foreignId('approved_by')->constrained('users')->onDelete('cascade');

            $table->date('po_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beef_purchase_orders');
    }
};
