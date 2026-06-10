<?php

namespace App\Filament\Admin\Resources\BeefStockMovementResource\Pages;

use App\Filament\Admin\Resources\BeefStockMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListBeefStockMovements extends ListRecords
{
    protected static string $resource = BeefStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
