<?php

namespace App\Filament\Exports;

use App\Models\Payable;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PayableExporter extends Exporter
{
    protected static ?string $model = Payable::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Date'),
            ExportColumn::make('document_number')->label('Document No.'),
            ExportColumn::make('supplier.name')->label('Supplier'),
            ExportColumn::make('amount')->label('Total Amount (Rp)'),
            ExportColumn::make('paid_amount')->label('Paid Amount (Rp)'),
            ExportColumn::make('balance')->label('Outstanding Balance (Rp)'),
            ExportColumn::make('due_date')->label('Due Date'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('note')->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payables export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
