<?php

namespace App\Filament\Exports;

use App\Models\SalesOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SalesOrderExporter extends Exporter
{
    protected static ?string $model = SalesOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('so_number')->label('SO Number'),
            ExportColumn::make('customer.name')->label('Customer'),
            ExportColumn::make('delivery_date')->label('Delivery Date'),
            ExportColumn::make('po_number')->label('PO Number'),
            ExportColumn::make('shipping_address')->label('Shipping Address'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('note')->label('Note'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your sales order export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
