<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSegment extends EditRecord
{
    protected static string $resource = CustomerSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
