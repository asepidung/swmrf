<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockTake extends ViewRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(fn () => StockTakeResource::getUrl('index')),
                
            Actions\Action::make('print')
                ->label(__('Print Report'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('stock-take.print', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
                
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'DRAFT' || $this->record->status === 'IN_PROGRESS'),
                
            Actions\DeleteAction::make()
                ->visible(fn () => in_array($this->record->status, ['DRAFT', 'IN_PROGRESS']) && $this->record->items()->whereIn('status', ['MATCHED', 'UNEXPECTED'])->count() === 0),
        ];
    }
}
