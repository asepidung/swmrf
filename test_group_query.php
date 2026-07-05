<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MaterialUsage;

try {
    $query = MaterialUsage::query()
        ->select('usageable_type', 'usageable_id')
        ->selectRaw('MIN(id) as id')
        ->selectRaw('MAX(created_at) as created_at')
        ->selectRaw('COUNT(id) as material_count')
        ->selectRaw('SUM(qty) as total_qty')
        ->groupBy('usageable_type', 'usageable_id');

    $paginated = $query->paginate(10);
    echo "Pagination successful. Total pages: " . $paginated->lastPage() . "\n";
    echo "Total items: " . $paginated->total() . "\n";
    foreach ($paginated->items() as $item) {
        echo "ID: {$item->id}, Type: {$item->usageable_type}, Count: {$item->material_count}, Qty: {$item->total_qty}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
