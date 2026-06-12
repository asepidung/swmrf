<?php

namespace App\Filament\Admin\Resources\PriceListResource\Pages;

use App\Filament\Admin\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPriceList extends ViewRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('success')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('print.pricelist', $record->priceList))
                ->openUrlInNewTab()
                ->visible(fn ($record) => $record->priceList && $record->priceList->items()->exists()),
            Actions\EditAction::make()
                ->label(__('Edit'))
                ->color('primary'),
        ];
    }
}
