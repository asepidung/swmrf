<?php

namespace App\Filament\Admin\Resources\MaterialAdjustmentResource\Pages;

use App\Filament\Admin\Resources\MaterialAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialAdjustment extends EditRecord
{
    protected static string $resource = MaterialAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        
        // Remove the default cancel action at the bottom
        return array_filter($actions, function ($action) {
            return $action->getName() !== 'cancel';
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
