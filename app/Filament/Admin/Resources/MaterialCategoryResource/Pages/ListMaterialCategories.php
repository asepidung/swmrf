<?php

namespace App\Filament\Admin\Resources\MaterialCategoryResource\Pages;

use App\Filament\Admin\Resources\MaterialCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialCategories extends ListRecords
{
    protected static string $resource = MaterialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
