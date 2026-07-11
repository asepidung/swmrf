<?php

namespace App\Filament\Exports;

use App\Models\PurchaseCattleItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseCattleItemExporter extends Exporter
{
    protected static ?string $model = PurchaseCattleItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('purchaseCattle.document_number')->label('PO Number'),
            ExportColumn::make('purchaseCattle.created_at')->label('PO Date')->formatStateUsing(fn ($state) => $state?->format('d-M-Y')),
            ExportColumn::make('purchaseCattle.supplier.name')->label('Supplier'),
            ExportColumn::make('cattleClass.name')->label('Cattle Class'),
            ExportColumn::make('qty')->label('Qty (Head)'),
            ExportColumn::make('price')->label('Price'),
            ExportColumn::make('subtotal')->label('Subtotal'),
            ExportColumn::make('item_notes')->label('Item Note'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase cattle detail export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}