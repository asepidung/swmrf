<?php

namespace App\Filament\Resources\CattleCategoryResource\Pages;

use App\Filament\Resources\CattleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCattleCategory extends CreateRecord
{
    protected static string $resource = CattleCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
