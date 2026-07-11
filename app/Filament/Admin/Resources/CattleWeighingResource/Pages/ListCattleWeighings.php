<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattleWeighings extends ListRecords
{
    protected static string $resource = CattleWeighingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('draft')
                ->label(__('Draft Weighing'))
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->url(fn (): string => CattleWeighingResource::getUrl('draft')),
        ];
    }
}
