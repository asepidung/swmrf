<?php

namespace App\Filament\Admin\Resources\BarcodeCostResource\Pages;

use App\Filament\Admin\Resources\BarcodeCostResource;
use App\Models\BarcodeCost;
use App\Support\BarcodeCosts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBarcodeCosts extends ListRecords
{
    protected static string $resource = BarcodeCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('value_opening_stock')
                ->label(__('Value opening stock'))
                ->icon('heroicon-o-calculator')
                ->requiresConfirmation()
                ->modalDescription(__('Stock that has no cost yet is valued with the last locked costing of its product. Products never costed stay unvalued, to be filled in manually. Barcodes that already have a cost are not touched.'))
                ->authorize(fn (): bool => auth()->user()?->can('update', new BarcodeCost()) ?? false)
                ->action(function (): void {
                    $result = BarcodeCosts::valueOpeningStock();

                    Notification::make()
                        ->title(__(':valued barcodes valued, :unvalued left unvalued.', $result))
                        ->success()
                        ->send();
                }),
        ];
    }
}
