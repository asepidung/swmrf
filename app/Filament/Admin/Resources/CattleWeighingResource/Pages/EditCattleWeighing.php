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
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => \App\Models\Carcass::where('cattle_weighing_id', $record->id)->exists()),
            Actions\ForceDeleteAction::make()
                ->disabled(fn ($record) => \App\Models\Carcass::where('cattle_weighing_id', $record->id)->exists()),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if (\App\Models\Carcass::where('cattle_weighing_id', $this->getRecord()->id)->exists()) {
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

    public function mount($record): void
    {
        parent::mount($record);

        if (\App\Models\Carcass::where('cattle_weighing_id', $this->getRecord()->id)->exists()) {
            \Filament\Notifications\Notification::make()
                ->title(__('This Cattle Weighing has been processed into Carcass and is now read-only.'))
                ->warning()
                ->send();
        }
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $record->calculateAndSaveFinancialLoss();
    }
}
