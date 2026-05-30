<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattleReceivings extends ListRecords
{
    protected static string $resource = CattleReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('draft')
                ->label(__('Draft / Select PO'))
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->url(fn (): string => $this->getResource()::getUrl('draft')),
        ];
    }
}
