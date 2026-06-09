<?php

namespace App\Filament\Admin\Resources\MaterialStockMovementResource\Pages;

use App\Filament\Admin\Resources\MaterialStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialStockMovement extends EditRecord
{
    protected static string $resource = MaterialStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
