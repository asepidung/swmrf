<?php

namespace App\Filament\Resources\BoningResource\Pages;

use App\Filament\Resources\BoningResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoning extends EditRecord
{
    protected static string $resource = BoningResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
