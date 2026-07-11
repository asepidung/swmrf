<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCattleWeighing extends EditRecord
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
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount($record): void
    {
        parent::mount($record);

        if (\App\Models\Carcass::where('cattle_weighing_id', $this->getRecord()->id)->exists()) {
            \Filament\Notifications\Notification::make()
                ->title(__('This Cattle Weighing cannot be edited or deleted because it has already been processed into Carcass.'))
                ->danger()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $record->calculateAndSaveFinancialLoss();
    }
}
