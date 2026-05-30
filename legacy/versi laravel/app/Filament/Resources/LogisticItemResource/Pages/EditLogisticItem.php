<?php

namespace App\Filament\Resources\LogisticItemResource\Pages;

use App\Filament\Resources\LogisticItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogisticItem extends EditRecord
{
    protected static string $resource = LogisticItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
