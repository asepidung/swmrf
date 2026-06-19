<?php

namespace App\Filament\Exports;

use App\Models\InvoiceItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class InvoiceItemExporter extends Exporter
{
    protected static ?string $model = InvoiceItem::class;

    public static function modifyQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->with(['invoice.customer', 'product']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('invoice.invoice_number')->label('Invoice Number'),
            ExportColumn::make('invoice.customer.name')->label('Customer'),
            ExportColumn::make('invoice.invoice_date')->label('Invoice Date'),
            ExportColumn::make('product.name')->label('Product'),
            ExportColumn::make('weight')->label('Weight (Kg)'),
            ExportColumn::make('price')->label('Price (Rp)'),
            ExportColumn::make('discount_percent')->label('Disc %'),
            ExportColumn::make('discount_rp')->label('Disc Rp'),
            ExportColumn::make('amount')->label('Amount (Rp)'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice items export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
