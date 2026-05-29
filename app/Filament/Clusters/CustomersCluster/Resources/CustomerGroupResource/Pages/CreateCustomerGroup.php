<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerGroup extends CreateRecord
{
    protected static string $resource = CustomerGroupResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
