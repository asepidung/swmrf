<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseCattle extends EditRecord
{
    protected static string $resource = PurchaseCattleResource::class;

    protected function mutateFormDataBeforeValidation(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                if (empty($item['cattle_class_id'])) {
                    unset($data['items'][$key]);
                }
            }
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label('Print')
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('po-cattle.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => $record->receivings()->exists()),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->getRecord()->receivings()->exists()) {
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
