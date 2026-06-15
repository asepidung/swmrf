<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Actions\Action;

class ListDeliveryOrders extends ListRecords
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::getResource()::getUrl('detail-list')),
            Actions\Action::make('draft')
                ->label(__('Draft DO'))
                ->color('warning')
                ->url(static::getResource()::getUrl('draft')),
        ];
    }
}
