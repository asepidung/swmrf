<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use Filament\Pages\SubNavigationPosition;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
