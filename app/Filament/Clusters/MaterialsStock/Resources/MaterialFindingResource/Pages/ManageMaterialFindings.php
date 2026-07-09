<?php

namespace App\Filament\Clusters\MaterialsStock\Resources\MaterialFindingResource\Pages;

use App\Filament\Clusters\MaterialsStock\Resources\MaterialFindingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMaterialFindings extends ManageRecords
{
    protected static string $resource = MaterialFindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
