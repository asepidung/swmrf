<?php

namespace App\Filament\Exports;

use App\Models\PurchaseMaterialItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseMaterialItemExporter extends Exporter
{
    protected static ?string $model = PurchaseMaterialItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('purchaseMaterial.po_number')->label('PO Number'),
            ExportColumn::make('purchaseMaterial.po_date')->label('PO Date'),
            ExportColumn::make('purchaseMaterial.supplier.name')->label('Supplier'),
            ExportColumn::make('material.name')->label('Material'),
            ExportColumn::make('qty')->label('Qty'),
            ExportColumn::make('price')->label('Price'),
            ExportColumn::make('subtotal')->label('Subtotal'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase material item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
