<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
