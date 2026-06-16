<?php

namespace App\Filament\Admin\Resources\PurchaseProductResource\Pages;

use App\Filament\Admin\Resources\PurchaseProductResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseProducts extends ListRecords
{
    protected static string $resource = PurchaseProductResource::class;

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
