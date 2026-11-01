<?php

namespace App\Filament\Admin\Resources\MeatProcessingMovementResource\Pages;

use App\Filament\Admin\Resources\BeefStockMovementResource\Pages\ListBeefStockMovements;
use App\Filament\Admin\Resources\MeatProcessingMovementResource;

class ListMeatProcessingMovements extends ListBeefStockMovements
{
    protected static string $resource = MeatProcessingMovementResource::class;
}
