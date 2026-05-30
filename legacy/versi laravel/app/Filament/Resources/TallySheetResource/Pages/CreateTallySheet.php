<?php

namespace App\Filament\Resources\TallySheetResource\Pages;

use App\Filament\Resources\TallySheetResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTallySheet extends CreateRecord
{
    protected static string $resource = TallySheetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
