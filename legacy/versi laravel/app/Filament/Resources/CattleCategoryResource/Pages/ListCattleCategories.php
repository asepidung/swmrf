<?php

namespace App\Filament\Resources\CattleCategoryResource\Pages;

use App\Filament\Resources\CattleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattleCategories extends ListRecords
{
    protected static string $resource = CattleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
