<?php

namespace App\Filament\Exports;

use App\Models\SalesOrderItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SalesOrderItemExporter extends Exporter
{
    protected static ?string $model = SalesOrderItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('salesOrder.so_number')->label('SO Number'),
            ExportColumn::make('salesOrder.customer.name')->label('Customer'),
            ExportColumn::make('salesOrder.delivery_date')->label('Delivery Date'),
            ExportColumn::make('product.name')->label('Product'),
            ExportColumn::make('weight')->label('Weight'),
            ExportColumn::make('price')->label('Price'),
            ExportColumn::make('discount')->label('Discount (%)'),
            ExportColumn::make('note')->label('Note'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your sales order item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
