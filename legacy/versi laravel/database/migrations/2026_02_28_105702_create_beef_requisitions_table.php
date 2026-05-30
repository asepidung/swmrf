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
        Schema::create('beef_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();

            /* Status flow: Requested, Pending Finance, Returned to Purchasing, PO Created, Rejected */
            $table->string('status')->default('Requested');
            $table->text('reject_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beef_requisitions');
    }
};
