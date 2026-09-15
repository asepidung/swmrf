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
            // Sebelumnya DeleteAction polos -- galat SQL mentah kalau
            // segmennya masih dipakai. Sama seperti EditWarehouse.
            Actions\DeleteAction::make()
                ->action(function () {
                    if (\App\Support\MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Customer Segment').' '.$this->record->name,
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
