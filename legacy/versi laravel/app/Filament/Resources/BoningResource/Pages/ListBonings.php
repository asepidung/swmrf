<?php

namespace App\Filament\Resources\BoningResource\Pages;

use App\Filament\Resources\BoningResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBonings extends ListRecords
{
    protected static string $resource = BoningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
