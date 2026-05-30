<?php

namespace App\Filament\Resources\RepackResource\Pages;

use App\Filament\Resources\RepackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRepacks extends ListRecords
{
    protected static string $resource = RepackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
