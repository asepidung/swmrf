<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCattleReceiving extends EditRecord
{
    protected static string $resource = CattleReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
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
}
