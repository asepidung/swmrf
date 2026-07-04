<?php

namespace App\Filament\Exports;

use App\Models\MaterialUsage;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaterialUsageExporter extends Exporter
{
    protected static ?string $model = MaterialUsage::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Usage Date'),
            ExportColumn::make('usageable_type')->label('Reference Type'),
            ExportColumn::make('usageable_id')->label('Reference ID'),
            ExportColumn::make('material.name')->label('Material'),
            ExportColumn::make('qty')->label('Qty (Minus)'),
            ExportColumn::make('note')->label('Note'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your material usage export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
