<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountPayable extends ViewRecord
{
    protected static string $resource = AccountPayableResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    protected function getHeaderActions(): array
    {
        return [
            // Actions\EditAction::make(), <-- HAPUS ATAU COMMENT BARIS INI JUGA!
        ];
    }
}
