<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialStockTake extends ViewRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record) => in_array($record->status, ['DRAFT', 'IN_PROGRESS'])),

            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
                
            Actions\Action::make('print')
                ->label(__('Print Opname'))
                ->color('success')
                ->icon('heroicon-o-printer')
                ->url(fn ($record) => route('material-stock-take.print', ['id' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }
}
