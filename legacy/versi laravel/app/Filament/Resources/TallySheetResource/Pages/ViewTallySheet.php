<?php

namespace App\Filament\Resources\TallySheetResource\Pages;

use App\Filament\Resources\TallySheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTallySheet extends ViewRecord
{
    protected static string $resource = TallySheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
