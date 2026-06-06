<?php

namespace App\Filament\Exports;

use App\Models\ProductRequisitionItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProductRequisitionItemExporter extends Exporter
{
    protected static ?string $model = ProductRequisitionItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('productRequisition.created_at')
                ->label('Request Date'),
            ExportColumn::make('productRequisition.document_number')
                ->label('No. Request'),
            ExportColumn::make('productRequisition.supplier.name')
                ->label('Supplier'),
            ExportColumn::make('product.name')
                ->label('Item Name'),
            ExportColumn::make('qty')
                ->label('Qty'),
            ExportColumn::make('price')
                ->label('Price'),
            ExportColumn::make('productRequisition.status')
                ->label('Status'),
            ExportColumn::make('productRequisition.user.name')
                ->label('User'),
            ExportColumn::make('note')
                ->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product requisition item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
