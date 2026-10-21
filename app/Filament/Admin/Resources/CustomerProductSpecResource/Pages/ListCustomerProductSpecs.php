<?php

namespace App\Filament\Admin\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Admin\Resources\CustomerProductSpecResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerProductSpecs extends ListRecords
{
    protected static string $resource = CustomerProductSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::$resource::getUrl('detail-list')),
        ];
    }
}
