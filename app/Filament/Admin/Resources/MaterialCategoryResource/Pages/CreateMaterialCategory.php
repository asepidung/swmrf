<?php

namespace App\Filament\Admin\Resources\MaterialCategoryResource\Pages;

use App\Filament\Admin\Resources\MaterialCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialCategory extends CreateRecord
{
    protected static string $resource = MaterialCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
