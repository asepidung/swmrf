<?php

namespace App\Filament\Resources\CattlePurchaseOrderResource\Pages;

use App\Filament\Resources\CattlePurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattlePurchaseOrders extends ListRecords
{
    protected static string $resource = CattlePurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
