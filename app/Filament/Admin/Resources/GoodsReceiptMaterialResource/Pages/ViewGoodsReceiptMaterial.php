<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGoodsReceiptMaterial extends ViewRecord
{
    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->tooltip(__('Print'))
                ->hiddenLabel()
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record): string => route('goods-receipt-material.print', $record))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->tooltip(__('Back to List'))
                ->hiddenLabel()
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
