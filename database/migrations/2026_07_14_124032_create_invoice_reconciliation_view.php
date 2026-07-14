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
        DB::statement("
            CREATE OR REPLACE VIEW invoice_reconciliation_view AS
            SELECT 
                CONCAT('item_', invoice_items.id) AS id,
                invoice_items.invoice_id,
                invoice_items.product_id,
                NULL AS charge_name,
                invoice_items.weight,
                invoice_items.price,
                invoice_items.discount_percent,
                invoice_items.discount_rp,
                invoice_items.amount,
                invoice_items.created_at,
                invoice_items.updated_at,
                'product' AS row_type
            FROM invoice_items
            UNION ALL
            SELECT 
                CONCAT('charge_', invoice_additional_charges.id) AS id,
                invoice_additional_charges.invoice_id,
                NULL AS product_id,
                invoice_additional_charges.name AS charge_name,
                invoice_additional_charges.qty AS weight,
                invoice_additional_charges.price,
                invoice_additional_charges.discount_percent,
                invoice_additional_charges.discount_rp,
                invoice_additional_charges.amount,
                invoice_additional_charges.created_at,
                invoice_additional_charges.updated_at,
                'charge' AS row_type
            FROM invoice_additional_charges
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS invoice_reconciliation_view");
    }
};
