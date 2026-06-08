<?php

namespace App\Filament\Exports;

use App\Models\GoodsReceiptMaterial;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GoodsReceiptMaterialExporter extends Exporter
{
    protected static ?string $model = GoodsReceiptMaterial::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('gr_number'),
            ExportColumn::make('purchase_material_id'),
            ExportColumn::make('supplier_id'),
            ExportColumn::make('receive_date'),
            ExportColumn::make('sj_number'),
            ExportColumn::make('note'),
            ExportColumn::make('created_by'),
            ExportColumn::make('deleted_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your goods receipt material export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
