<?php

namespace App\Filament\Admin\Resources\PriceListResource\Pages;

use App\Filament\Admin\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPriceLists extends ListRecords
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::getResource()::getUrl('detail-list')),
        ];
    }
}
