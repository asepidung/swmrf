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
            Actions\CreateAction::make()
                ->label(__('Create')),
        ];
    }
}
