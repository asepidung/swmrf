<?php

namespace App\Filament\Resources\CarcassResource\Pages;

use App\Filament\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCarcass extends EditRecord
{
    protected static string $resource = CarcassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
