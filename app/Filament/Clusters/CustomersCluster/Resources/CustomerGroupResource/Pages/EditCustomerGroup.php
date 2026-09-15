<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerGroup extends EditRecord
{
    protected static string $resource = CustomerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sebelumnya DeleteAction polos -- galat SQL mentah kalau
            // grupnya masih dipakai. Sama seperti EditWarehouse.
            Actions\DeleteAction::make()
                ->action(function () {
                    if (\App\Support\MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Customer Group').' '.$this->record->name,
                    )) {
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
