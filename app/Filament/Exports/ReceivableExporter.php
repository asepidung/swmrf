<?php

namespace App\Filament\Exports;

use App\Models\Receivable;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ReceivableExporter extends Exporter
{
    protected static ?string $model = Receivable::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('invoice.invoice_number')->label('Invoice Number'),
            ExportColumn::make('customer.name')->label('Customer'),
            ExportColumn::make('invoice.invoice_date')->label('Invoice Date'),
            ExportColumn::make('invoice.term_of_payment')->label('T.O.P (Days)'),
            ExportColumn::make('invoice.due_date')->label('Due Date'),
            ExportColumn::make('invoice.balance')->label('Outstanding Balance (Rp)'),
            ExportColumn::make('invoice.status')->label('Status'),
            ExportColumn::make('created_at')->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your receivables export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
