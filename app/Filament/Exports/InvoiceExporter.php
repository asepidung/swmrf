<?php

namespace App\Filament\Exports;

use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('invoice_number')->label('Invoice Number'),
            ExportColumn::make('customer.name')->label('Customer'),
            ExportColumn::make('invoice_date')->label('Invoice Date'),
            ExportColumn::make('due_date')->label('Due Date'),
            ExportColumn::make('po_number')->label('PO Number'),
            ExportColumn::make('delivery_order_number')->label('DO Number'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('total_weight')->label('Total Weight (Kg)'),
            ExportColumn::make('subtotal')->label('Subtotal (Rp)'),
            ExportColumn::make('total_discount')->label('Discount (Rp)'),
            ExportColumn::make('tax')->label('Tax (Rp)'),
            ExportColumn::make('charge')->label('Charge (Rp)'),
            ExportColumn::make('down_payment')->label('Down Payment (Rp)'),
            ExportColumn::make('balance')->label('Balance (Rp)'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
