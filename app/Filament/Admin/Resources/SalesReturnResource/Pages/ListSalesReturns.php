<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturns extends ListRecords
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::getResource()::getUrl('detail-list')),
        ];
    }
}
