<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\Concerns\KeepsCustomerInAGroup;
use App\Support\PriceListInvitation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use KeepsCustomerInAGroup;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            // Sembunyi kalau punya Sales Order (syarat bisnis lama), TAPI
            // itu tidak menutup kunci asing LAIN (invoice, receivable, dst)
            // -- galat SQL mentah tetap mungkin lolos dari sana. Dibungkus
            // sama seperti EditWarehouse untuk kunci asing di luar itu.
            Actions\DeleteAction::make()
                ->hidden(fn ($record) => $record->salesOrders()->exists())
                ->action(function () {
                    if (\App\Support\MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Customer').' '.$this->record->name,
                    )) {
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->ensureCustomerGroup($data);
    }

    /**
     * Memindahkan pelanggan ke grup lain ikut memindahkan price list yang
     * berlaku baginya, jadi grup tujuan yang belum punya harga perlu
     * diberitahukan di sini juga -- bukan hanya saat pelanggan dibuat.
     */
    protected function afterSave(): void
    {
        PriceListInvitation::offerFor($this->getRecord()->group);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
