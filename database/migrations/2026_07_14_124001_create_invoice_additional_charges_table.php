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
        Schema::create('invoice_additional_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_rp', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        // Copy existing data from JSON to new table
        $invoices = DB::table('invoices')->whereNotNull('additional_charges')->get();
        foreach ($invoices as $invoice) {
            if (empty($invoice->additional_charges)) continue;
            
            $charges = json_decode($invoice->additional_charges, true);
            if (is_array($charges) && count($charges) > 0) {
                foreach ($charges as $charge) {
                    if (empty($charge['name'])) continue;
                    DB::table('invoice_additional_charges')->insert([
                        'invoice_id' => $invoice->id,
                        'name' => $charge['name'] ?? 'Other Charge',
                        'qty' => $charge['qty'] ?? 1,
                        'price' => $charge['price'] ?? 0,
                        'discount_percent' => $charge['discount_percent'] ?? 0,
                        'discount_rp' => $charge['discount_rp'] ?? 0,
                        'amount' => $charge['amount'] ?? 0,
                        'created_at' => $invoice->created_at,
                        'updated_at' => $invoice->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_additional_charges');
    }
};
