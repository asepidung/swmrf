<?php

namespace App\Filament\Exports;

use App\Models\MaterialRequisitionItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaterialRequisitionItemExporter extends Exporter
{
    protected static ?string $model = MaterialRequisitionItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('requisition.created_at')->label('Request Date'),
            ExportColumn::make('requisition.document_number')->label('No. Request'),
            ExportColumn::make('requisition.supplier.name')->label('Supplier'),
            ExportColumn::make('material.name')->label('Item Name'),
            ExportColumn::make('qty')->label('Qty'),
            ExportColumn::make('price')->label('Price (Rp)'),
            ExportColumn::make('requisition.status')->label('Status'),
            ExportColumn::make('requisition.user.name')->label('User'),
            ExportColumn::make('note')->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your material requisition item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
