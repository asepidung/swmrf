<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW material_usage_headers AS
            SELECT 
                CONCAT(usageable_type, '_', usageable_id) as id,
                usageable_type,
                usageable_id,
                MIN(created_at) as created_at,
                MAX(updated_at) as updated_at,
                COUNT(material_id) as material_count,
                SUM(qty) as total_qty
            FROM material_usages
            GROUP BY usageable_type, usageable_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS material_usage_headers");
    }
};
