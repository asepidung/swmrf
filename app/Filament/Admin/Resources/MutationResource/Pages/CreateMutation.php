<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMutation extends CreateRecord
{
    protected static string $resource = MutationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('scan', ['record' => $this->record]);
    }
}
