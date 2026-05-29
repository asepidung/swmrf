<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['customer_group_id'])) {
            $group = \App\Models\CustomerGroup::firstOrCreate(['name' => strtoupper($data['name'])]);
            $data['customer_group_id'] = $group->id;
        }
        
        $data['name'] = strtoupper($data['name']);
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
