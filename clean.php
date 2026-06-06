<?php
$files = [
    'app/Models/CarcassItem.php',
    'app/Models/CattleReceivingItem.php',
    'app/Models/CattleWeighingItem.php',
    'app/Models/MaterialRequisitionItem.php',
    'app/Models/PurchaseCattleItem.php',
    'app/Models/FinancialLoss.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Remove Spatie imports
    $content = preg_replace('/use Spatie\\\\Activitylog\\\\Traits\\\\LogsActivity;\r?\n?/', '', $content);
    $content = preg_replace('/use Spatie\\\\Activitylog\\\\LogOptions;\r?\n?/', '', $content);
    
    // Remove traits
    $content = preg_replace('/use HasFactory, SoftDeletes, LogsActivity;/', 'use HasFactory, SoftDeletes;', $content);
    $content = preg_replace('/use HasFactory, LogsActivity;/', 'use HasFactory;', $content);
    $content = preg_replace('/use LogsActivity;/', '', $content);
    
    // Remove getActivitylogOptions method
    $content = preg_replace('/public function getActivitylogOptions\(\): LogOptions\s*\{[\s\S]*?\s*\}\r?\n?/', '', $content);
    
    file_put_contents($file, $content);
    echo "Cleaned $file\n";
}
