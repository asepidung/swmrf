<?php

namespace App\Filament\Resources\LogisticRequisitionResource\Pages;

use App\Filament\Resources\LogisticRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogisticRequisition extends EditRecord
{
    protected static string $resource = LogisticRequisitionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Memodifikasi instance record secara langsung sebelum proses penyimpanan ke database.
     */
    protected function beforeSave(): void
    {
        if ($this->record->status === 'Rejected') {
            $this->record->status = 'Requested';
            $this->record->reject_note = null;
        }
    }
}
