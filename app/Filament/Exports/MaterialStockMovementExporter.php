<?php

namespace App\Filament\Exports;

use App\Models\MaterialStockMovement;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaterialStockMovementExporter extends Exporter
{
    protected static ?string $model = MaterialStockMovement::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Timestamp'),
            ExportColumn::make('reference_document')->label('Reference Document'),
            ExportColumn::make('material.name')->label('Material'),
            ExportColumn::make('transaction_type')->label('Transaction Type'),
            ExportColumn::make('qty_in')->label('Qty In'),
            ExportColumn::make('qty_out')->label('Qty Out'),
            ExportColumn::make('balance')->label('Balance'),
            ExportColumn::make('note')->label('Note'),
            ExportColumn::make('creator.name')->label('Operator'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your stock movements export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
