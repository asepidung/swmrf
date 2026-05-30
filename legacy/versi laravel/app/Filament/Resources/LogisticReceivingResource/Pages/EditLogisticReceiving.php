<?php

namespace App\Filament\Resources\LogisticReceivingResource\Pages;

use App\Filament\Resources\LogisticReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogisticReceiving extends EditRecord
{
    protected static string $resource = LogisticReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
