<?php

namespace App\Filament\Admin\Resources\DeliveryPlanResource\Pages;

use App\Filament\Admin\Resources\DeliveryPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryPlan extends EditRecord
{
    protected static string $resource = DeliveryPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
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
}
