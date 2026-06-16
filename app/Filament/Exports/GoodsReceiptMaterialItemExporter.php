<?php

namespace App\Filament\Exports;

use App\Models\GoodsReceiptMaterialItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GoodsReceiptMaterialItemExporter extends Exporter
{
    protected static ?string $model = GoodsReceiptMaterialItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('goodsReceiptMaterial.gr_number')->label('GR Number'),
            ExportColumn::make('goodsReceiptMaterial.receive_date')->label('Receive Date'),
            ExportColumn::make('goodsReceiptMaterial.sj_number')->label('Surat Jalan'),
            ExportColumn::make('goodsReceiptMaterial.purchaseMaterial.po_number')->label('PO Number'),
            ExportColumn::make('goodsReceiptMaterial.supplier.name')->label('Supplier'),
            ExportColumn::make('material.name')->label('Material'),
            ExportColumn::make('qty_received')->label('Qty Received'),
            ExportColumn::make('price')->label('Price'),
            ExportColumn::make('subtotal')->label('Subtotal'),
            ExportColumn::make('goodsReceiptMaterial.createdBy.name')->label('Created By'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your material receipt item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
