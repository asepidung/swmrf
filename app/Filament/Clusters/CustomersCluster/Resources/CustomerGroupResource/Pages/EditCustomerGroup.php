<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCustomerGroup extends EditRecord
{
    protected static string $resource = CustomerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tombolnya disembunyikan saat grup masih dipakai (pola sama
            // EditCustomer menyembunyikan Delete saat ada salesOrders), DAN
            // ditolak ulang di sini -- lihat CustomerGroup::isInUse().
            Actions\DeleteAction::make()
                ->hidden(fn ($record) => $record->isInUse())
                ->action(function () {
                    $record = $this->getRecord();

                    if ($record->isInUse()) {
                        Notification::make()
                            ->title(__('This customer group cannot be deleted'))
                            ->body(__('It still has customers, a price list, receivables, or payments attached.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    if (MasterDataDeletion::attempt(fn () => $record->delete(), __('Customer Group').' '.$record->name)) {
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
