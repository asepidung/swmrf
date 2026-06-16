<?php

namespace App\Filament\Admin\Resources\PurchaseMaterialResource\Pages;

use App\Filament\Admin\Resources\PurchaseMaterialResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseMaterials extends ListRecords
{
    protected static string $resource = PurchaseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::$resource::getUrl('detail-list')),
        ];
    }
}
