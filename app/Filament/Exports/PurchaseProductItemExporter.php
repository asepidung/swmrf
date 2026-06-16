<?php

namespace App\Filament\Exports;

use App\Models\PurchaseProductItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseProductItemExporter extends Exporter
{
    protected static ?string $model = PurchaseProductItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('purchaseProduct.po_number')->label('PO Number'),
            ExportColumn::make('purchaseProduct.po_date')->label('PO Date'),
            ExportColumn::make('purchaseProduct.supplier.name')->label('Supplier'),
            ExportColumn::make('product.name')->label('Product'),
            ExportColumn::make('qty')->label('Qty'),
            ExportColumn::make('price')->label('Price'),
            ExportColumn::make('subtotal')->label('Subtotal'),
            ExportColumn::make('note')->label('Note'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase product item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
