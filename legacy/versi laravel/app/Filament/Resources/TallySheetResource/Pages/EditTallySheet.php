<?php

namespace App\Filament\Resources\TallySheetResource\Pages;

use App\Filament\Resources\TallySheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTallySheet extends EditRecord
{
    protected static string $resource = TallySheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
