<?php

namespace App\Filament\Admin\Resources\MaterialStockResource\Pages;

use App\Filament\Admin\Resources\MaterialStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialStocks extends ListRecords
{
    protected static string $resource = MaterialStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Read-only stock list
        ];
    }
}
