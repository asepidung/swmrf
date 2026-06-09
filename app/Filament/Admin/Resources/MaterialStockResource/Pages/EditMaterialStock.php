<?php

namespace App\Filament\Admin\Resources\MaterialStockResource\Pages;

use App\Filament\Admin\Resources\MaterialStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialStock extends EditRecord
{
    protected static string $resource = MaterialStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
