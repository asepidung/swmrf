<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerGroups extends ListRecords
{
    protected static string $resource = CustomerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
