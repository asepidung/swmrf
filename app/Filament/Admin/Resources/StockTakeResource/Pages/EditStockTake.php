<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockTake extends EditRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
