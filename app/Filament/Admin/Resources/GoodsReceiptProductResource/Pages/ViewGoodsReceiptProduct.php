<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGoodsReceiptProduct extends ViewRecord
{
    protected static string $resource = GoodsReceiptProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->tooltip('Print')
                ->hiddenLabel()
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record): string => route('goods-receipt-product.print', $record))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->tooltip('Back to List')
                ->hiddenLabel()
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
