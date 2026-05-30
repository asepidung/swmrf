<?php

namespace App\Filament\Resources\LogisticRequisitionResource\Pages;

use App\Filament\Resources\LogisticRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLogisticRequisition extends ViewRecord
{
    protected static string $resource = LogisticRequisitionResource::class;

    /* Pengaturan Header Aksi untuk Halaman View Murni */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
