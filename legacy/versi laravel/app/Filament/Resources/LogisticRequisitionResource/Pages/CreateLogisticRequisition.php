<?php

namespace App\Filament\Resources\LogisticRequisitionResource\Pages;

use App\Filament\Resources\LogisticRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLogisticRequisition extends CreateRecord
{
    protected static string $resource = LogisticRequisitionResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
