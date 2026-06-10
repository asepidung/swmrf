<?php

namespace App\Filament\Exports;

use App\Models\BeefStockMovement;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BeefStockMovementExporter extends Exporter
{
    protected static ?string $model = BeefStockMovement::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Timestamp'),
            ExportColumn::make('reference_document')->label('Reference Document'),
            ExportColumn::make('barcode')->label('Barcode'),
            ExportColumn::make('product.name')->label('Product'),
            ExportColumn::make('warehouse.name')->label('Warehouse'),
            ExportColumn::make('grade.name')->label('Grade'),
            ExportColumn::make('transaction_type')->label('Transaction Type'),
            ExportColumn::make('weight_in')->label('Weight In'),
            ExportColumn::make('weight_out')->label('Weight Out'),
            ExportColumn::make('pcs_in')->label('Pcs In'),
            ExportColumn::make('pcs_out')->label('Pcs Out'),
            ExportColumn::make('note')->label('Note'),
            ExportColumn::make('creator.name')->label('Operator'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your beef stock movements export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
