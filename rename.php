<?php

$dirs = [
    __DIR__ . '/app/Filament/Admin/Resources/ProductRequisitionResource',
    __DIR__ . '/app/Filament/Admin/Resources/PurchaseProductResource'
];

function processDir($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            processDir($path);
        } else {
            $content = file_get_contents($path);
            
            $replacements = [
                'MaterialRequisition' => 'ProductRequisition',
                'materialRequisition' => 'productRequisition',
                'material_requisition' => 'product_requisition',
                'Material Requisition' => 'Beef Request',
                'Material Request' => 'Beef Request',
                'Material' => 'Product',
                'material_id' => 'product_id',
                'materials' => 'products',
                'material' => 'product',
                'SWM-MPO' => 'SWM-BPO'
            ];
            
            foreach ($replacements as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }
            
            file_put_contents($path, $content);
            echo "Processed $path\n";
        }
    }
}

foreach ($dirs as $dir) {
    processDir($dir);
}
