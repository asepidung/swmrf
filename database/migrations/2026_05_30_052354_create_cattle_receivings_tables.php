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
        // Table Header: CattleReceiving
        Schema::create('cattle_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_number')->unique(); // Format CR#...
            $table->foreignId('purchase_cattle_id')
                ->constrained('purchase_cattles')
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();
            $table->date('receive_date');
            $table->string('doc_no')->nullable();
            $table->boolean('sv_ok')->default(false);
            $table->boolean('skkh_ok')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // Table Detail: CattleReceivingItem
        Schema::create('cattle_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cattle_receiving_id')
                ->constrained('cattle_receivings')
                ->cascadeOnDelete();
            $table->foreignId('cattle_class_id')
                ->constrained('cattle_classes')
                ->restrictOnDelete();
            $table->string('eartag')->index();
            $table->integer('initial_weight')->unsigned();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cattle_receiving_items');
        Schema::dropIfExists('cattle_receivings');
    }
};
