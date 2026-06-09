<?php

namespace App\Filament\Exports;

use App\Models\MaterialStock;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaterialStockExporter extends Exporter
{
    protected static ?string $model = MaterialStock::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('material.code')->label('Item Code'),
            ExportColumn::make('material.name')->label('Item Name'),
            ExportColumn::make('material.category.name')->label('Category'),
            ExportColumn::make('material.unit.name')->label('Unit'),
            ExportColumn::make('qty')->label('Current Stock'),
            ExportColumn::make('material.min_stock')->label('Min Stock'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your material stock export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
