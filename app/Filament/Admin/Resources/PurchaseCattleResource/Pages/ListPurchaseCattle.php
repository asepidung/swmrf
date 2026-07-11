<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseCattle extends ListRecords
{
    protected static string $resource = PurchaseCattleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create'),
            Actions\Action::make('detail-list')
                ->label('Detail')
                ->color('info')
                ->icon('heroicon-o-bars-3-bottom-left')
                ->url(fn (): string => static::getResource()::getUrl('detail-list')),
        ];
    }
}
