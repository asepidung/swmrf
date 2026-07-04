<?php

namespace App\Filament\Admin\Resources\MaterialAdjustmentResource\Pages;

use App\Filament\Admin\Resources\MaterialAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialAdjustments extends ListRecords
{
    protected static string $resource = MaterialAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Create')),
        ];
    }
}
