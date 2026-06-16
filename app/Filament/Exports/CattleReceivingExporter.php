<?php

namespace App\Filament\Exports;

use App\Models\CattleReceiving;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CattleReceivingExporter extends Exporter
{
    protected static ?string $model = CattleReceiving::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('receiving_number')->label('Receive Number'),
            ExportColumn::make('purchaseCattle.document_number')->label('PO Number'),
            ExportColumn::make('supplier.name')->label('Supplier'),
            ExportColumn::make('receive_date')->label('Receive Date'),
            ExportColumn::make('doc_no')->label('Document Number'),
            ExportColumn::make('sv_ok')
                ->label('SV OK')
                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            ExportColumn::make('skkh_ok')
                ->label('SKKH OK')
                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            ExportColumn::make('note')->label('Note'),
            ExportColumn::make('creator.name')->label('Received By'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your cattle receiving export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
