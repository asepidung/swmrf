<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCattleWeighing extends ViewRecord
{
    protected static string $resource = CattleWeighingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('cattle-weighing.print', $record))
                ->openUrlInNewTab(),
            Actions\EditAction::make()
                ->hidden(fn ($record) => $record->trashed()),
        ];
    }
}
