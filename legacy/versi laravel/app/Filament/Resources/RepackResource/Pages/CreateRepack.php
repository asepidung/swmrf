<?php

namespace App\Filament\Resources\RepackResource\Pages;

use App\Filament\Resources\RepackResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRepack extends CreateRecord
{
    protected static string $resource = RepackResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
