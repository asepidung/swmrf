<?php

namespace App\Filament\Admin\Resources\MaterialUnitResource\Pages;

use App\Filament\Admin\Resources\MaterialUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialUnits extends ListRecords
{
    protected static string $resource = MaterialUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
