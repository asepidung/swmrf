<?php

namespace App\Filament\Admin\Resources\MaterialUnitResource\Pages;

use App\Filament\Admin\Resources\MaterialUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialUnit extends CreateRecord
{
    protected static string $resource = MaterialUnitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
