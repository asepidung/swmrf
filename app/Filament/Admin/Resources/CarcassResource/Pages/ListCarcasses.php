<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCarcasses extends ListRecords
{
    protected static string $resource = CarcassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('draft')
                ->label('Draft Carcass')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->url(fn (): string => CarcassResource::getUrl('draft')),
        ];
    }
}
