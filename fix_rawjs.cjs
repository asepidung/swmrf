const fs = require('fs');

const filesToFix = [
    'd:/WebApps/swmrf/app/Filament/Admin/Resources/GoodsReceiptMaterialResource.php',
    'd:/WebApps/swmrf/app/Filament/Admin/Resources/GoodsReceiptMaterialResource/Pages/CreateGoodsReceiptMaterial.php',
    'd:/WebApps/swmrf/app/Filament/Admin/Resources/MaterialRequisitionResource.php',
    'd:/WebApps/swmrf/app/Filament/Admin/Resources/InvoiceResource.php',
    'd:/WebApps/swmrf/app/Filament/Admin/Resources/ProductRequisitionResource.php',
    'd:/WebApps/swmrf/app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/ManageMaterialStockTakeItems.php'
];

for (const file of filesToFix) {
    if (!fs.existsSync(file)) continue;
    let content = fs.readFileSync(file, 'utf8');
    
    // For InvoiceResource, only replace within Repeater (between Repeater::make('items') and Section::make('Summary'))
    if (file.includes('InvoiceResource.php')) {
        let parts = content.split("Section::make(__('Summary'))");
        let repeaterPart = parts[0];
        
        // Match mask, stripCharacters, and add numeric()
        repeaterPart = repeaterPart.replace(/->mask\(\\?Filament\\Support\\RawJs::make\('\$money[^\)]+\)'\)\)/g, '->numeric()');
        repeaterPart = repeaterPart.replace(/->mask\(RawJs::make\('\$money[^\)]+\)'\)\)/g, '->numeric()');
        repeaterPart = repeaterPart.replace(/->stripCharacters\('\.'\)\s*\n/g, '');
        
        parts[0] = repeaterPart;
        content = parts.join("Section::make(__('Summary'))");
    } else {
        content = content.replace(/->mask\(\\?Filament\\Support\\RawJs::make\('\$money[^\)]+\)'\)\)/g, '->numeric()');
        content = content.replace(/->mask\(RawJs::make\('\$money[^\)]+\)'\)\)/g, '->numeric()');
        content = content.replace(/->stripCharacters\('\.'\)\s*\n/g, '');
    }
    
    fs.writeFileSync(file, content);
    console.log("Fixed " + file);
}
