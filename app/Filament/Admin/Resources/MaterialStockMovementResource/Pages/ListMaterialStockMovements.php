<?php

namespace App\Filament\Admin\Resources\MaterialStockMovementResource\Pages;

use App\Filament\Admin\Resources\MaterialStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialStockMovements extends ListRecords
{
    protected static string $resource = MaterialStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Read-only stock movements list
        ];
    }
}
