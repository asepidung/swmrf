<?php

namespace App\Filament\Exports;

use App\Models\DeliveryPlan;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class DeliveryPlanExporter extends Exporter
{
    protected static ?string $model = DeliveryPlan::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('delivery_date')->label('Delivery Date'),
            ExportColumn::make('customer.name')->label('Customer'),
            ExportColumn::make('sales_orders_count')->label('Total PO'),
            ExportColumn::make('total_qty')->label('Qty (Kg)'),
            ExportColumn::make('driver')->label('Driver'),
            ExportColumn::make('armada')->label(__('Fleet')),
            ExportColumn::make('load_time')->label('Jam Loading'),
            ExportColumn::make('notes')->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your delivery plan export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
