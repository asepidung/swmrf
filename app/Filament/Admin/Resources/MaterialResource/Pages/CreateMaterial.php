<?php

namespace App\Filament\Admin\Resources\MaterialResource\Pages;

use App\Filament\Admin\Resources\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterial extends CreateRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
