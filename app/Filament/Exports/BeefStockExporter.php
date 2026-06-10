<?php

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BeefStockExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')->label('Code'),
            ExportColumn::make('name')->label('Product Name'),
            ExportColumn::make('chill_jonggol')->label('CHILL (J)'),
            ExportColumn::make('frozen_jonggol')->label('FROZEN (J)'),
            ExportColumn::make('chill_perum')->label('CHILL (P)'),
            ExportColumn::make('frozen_perum')->label('FROZEN (P)'),
            ExportColumn::make('total_qty')->label('Total'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your beef stocks export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
