<?php

namespace App\Filament\Resources\LogisticPurchaseOrderResource\Pages;

use App\Filament\Resources\LogisticPurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticPurchaseOrders extends ListRecords
{
    protected static string $resource = LogisticPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
