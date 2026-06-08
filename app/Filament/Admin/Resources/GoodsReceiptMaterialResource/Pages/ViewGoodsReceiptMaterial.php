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
                ->tooltip('Print')
                ->hiddenLabel()
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url('#') // Optional implementation for Print
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
