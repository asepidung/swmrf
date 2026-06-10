<?php

namespace App\Filament\Admin\Resources\BeefStockMovementResource\Pages;

use App\Filament\Admin\Resources\BeefStockMovementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBeefStockMovement extends ViewRecord
{
    protected static string $resource = BeefStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
