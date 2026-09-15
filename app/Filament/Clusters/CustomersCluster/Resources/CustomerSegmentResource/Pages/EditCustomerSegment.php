<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSegment extends EditRecord
{
    protected static string $resource = CustomerSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // customers.customer_segment_id adalah restrictOnDelete() DAN
            // NOT NULL -- menghapus segment yang masih dipakai satu saja
            // Customer sebelumnya menampilkan galat SQL mentah.
            Actions\DeleteAction::make()
                ->action(function () {
                    $record = $this->getRecord();

                    if (MasterDataDeletion::attempt(fn () => $record->delete(), __('Customer Segment').' '.$record->name)) {
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
