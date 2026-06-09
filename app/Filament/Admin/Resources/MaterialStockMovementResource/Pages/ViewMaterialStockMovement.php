<?php

namespace App\Filament\Admin\Resources\MaterialStockMovementResource\Pages;

use App\Filament\Admin\Resources\MaterialStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialStockMovement extends ViewRecord
{
    protected static string $resource = MaterialStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
        ];
    }
}
