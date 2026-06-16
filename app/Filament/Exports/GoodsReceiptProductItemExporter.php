<?php

namespace App\Filament\Exports;

use App\Models\GoodsReceiptProductItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GoodsReceiptProductItemExporter extends Exporter
{
    protected static ?string $model = GoodsReceiptProductItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('goodsReceiptProduct.gr_number')->label('GR Number'),
            ExportColumn::make('goodsReceiptProduct.receive_date')->label('Receive Date'),
            ExportColumn::make('goodsReceiptProduct.sj_number')->label('Surat Jalan'),
            ExportColumn::make('goodsReceiptProduct.purchaseProduct.po_number')->label('PO Number'),
            ExportColumn::make('goodsReceiptProduct.supplier.name')->label('Supplier'),
            ExportColumn::make('barcode')->label('Barcode'),
            ExportColumn::make('product.name')->label('Product'),
            ExportColumn::make('grade.name')->label('Grade'),
            ExportColumn::make('weight')->label('Weight'),
            ExportColumn::make('qty_pcs')->label('Pcs'),
            ExportColumn::make('price')->label('Price'),
            ExportColumn::make('subtotal')->label('Subtotal'),
            ExportColumn::make('goodsReceiptProduct.createdBy.name')->label('Created By'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your beef receipt item export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
