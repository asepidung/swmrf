<?php

namespace App\Filament\Exports;

use App\Models\PurchaseMaterial;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseMaterialExporter extends Exporter
{
    protected static ?string $model = PurchaseMaterial::class;

        public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Date'),
            ExportColumn::make('document_number')->label('Document No.'),
            ExportColumn::make('supplier.name')->label('Supplier'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('total_amount')->label('Total Amount (Rp)'),
            ExportColumn::make('note')->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase material export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
