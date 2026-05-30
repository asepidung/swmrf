<?php

namespace App\Filament\Resources\CattleCategoryResource\Pages;

use App\Filament\Resources\CattleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCattleCategory extends EditRecord
{
    protected static string $resource = CattleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
