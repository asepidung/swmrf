<?php

namespace App\Filament\Admin\Resources\MeatProcessingMovementResource\Pages;

use App\Filament\Admin\Resources\BeefStockMovementResource\Pages\ViewBeefStockMovement;
use App\Filament\Admin\Resources\MeatProcessingMovementResource;

class ViewMeatProcessingMovement extends ViewBeefStockMovement
{
    protected static string $resource = MeatProcessingMovementResource::class;
}
