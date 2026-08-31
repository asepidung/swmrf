<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use Filament\Actions;
use App\Filament\Admin\Resources\CattleReceivingResource\Concerns\SavesUniqueEartags;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCattleReceiving extends EditRecord
{
    use SavesUniqueEartags;

    protected static string $resource = CattleReceivingResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveGuardingEartags(fn (): Model => parent::handleRecordUpdate($record, $data));
    }



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
                ->url(fn ($record): string => route('cattle-receiving.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => $record->weighing()->exists()),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->getRecord()->weighing()->exists()) {
            return [];
        }
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
