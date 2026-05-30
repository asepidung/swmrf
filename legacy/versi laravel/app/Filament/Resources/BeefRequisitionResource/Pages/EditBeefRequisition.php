<?php

namespace App\Filament\Resources\BeefRequisitionResource\Pages;

use App\Filament\Resources\BeefRequisitionResource;
use Filament\Resources\Pages\EditRecord;

class EditBeefRequisition extends EditRecord
{
    protected static string $resource = BeefRequisitionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->record->status, ['Rejected', 'Returned to Purchasing'])) {
            $data['status'] = 'Requested';
            $data['reject_note'] = null;
        }

        return $data;
    }
}
