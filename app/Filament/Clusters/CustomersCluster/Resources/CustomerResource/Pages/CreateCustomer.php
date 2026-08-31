<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\Concerns\KeepsCustomerInAGroup;
use App\Support\PriceListInvitation;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use KeepsCustomerInAGroup;

    protected static string $resource = CustomerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->ensureCustomerGroup($data);
    }

    /**
     * Pelanggan baru hampir selalu berarti grup baru, dan grup baru berarti
     * belum ada satu harga pun. Ditawarkan sekarang supaya price list-nya
     * sudah siap sebelum Sales Order pertama dibuat, bukan sesudahnya.
     */
    protected function afterCreate(): void
    {
        PriceListInvitation::offerFor($this->getRecord()->group);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
