<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\CustomerGroup; // Pastikan model CustomerGroup di-import
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        if (blank($data['customer_group_id'] ?? null)) {

            $newGroup = CustomerGroup::create([
                'name' => strtoupper($data['name']),
            ]);

            $data['customer_group_id'] = $newGroup->id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
