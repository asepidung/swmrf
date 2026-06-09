<?php

namespace App\Filament\Admin\Resources\MaterialStockResource\Pages;

use App\Filament\Admin\Resources\MaterialStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialStock extends ViewRecord
{
    protected static string $resource = MaterialStockResource::class;

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
