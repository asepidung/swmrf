<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerProductSpecResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerProductSpecs extends ListRecords
{
    protected static string $resource = CustomerProductSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('Create')),
        ];
    }
}
