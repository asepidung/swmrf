<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerSegment extends CreateRecord
{
    protected static string $resource = CustomerSegmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
