<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
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
    echo "View created successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
