<?php

namespace App\Filament\Resources\CattleWeighingResource\Pages;

use App\Filament\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattleWeighings extends ListRecords
{
    protected static string $resource = CattleWeighingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('draft')
                ->label('Draft / Select GRC')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->url(CattleWeighingResource::getUrl('draft')),
        ];
    }
}
