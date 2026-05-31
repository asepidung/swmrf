<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseCattle extends ViewRecord
{
    protected static string $resource = PurchaseCattleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->url(fn() => static::getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('po-cattle.print', $record))
                ->openUrlInNewTab(),
        ];
    }
}
