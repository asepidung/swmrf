<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerProductSpecResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerProductSpec extends CreateRecord
{
    protected static string $resource = CustomerProductSpecResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
