<?php

namespace App\Filament\Resources\CarcassResource\Pages;

use App\Filament\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCarcasses extends ListRecords
{
    protected static string $resource = CarcassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('draft')
                ->label('Draft / Antrean Potong')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->url(CarcassResource::getUrl('draft')),
        ];
    }
}
