<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource;
use App\Support\PriceListInvitation;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerGroup extends CreateRecord
{
    protected static string $resource = CustomerGroupResource::class;

    /**
     * Grup adalah pemilik price list, jadi grup yang baru lahir selalu
     * belum punya harga sama sekali. Ditawarkan langsung supaya tidak
     * baru teringat saat Sales Order pertama sudah terisi Rp 0 semua.
     */
    protected function afterCreate(): void
    {
        PriceListInvitation::offerFor($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
