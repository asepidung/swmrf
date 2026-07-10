<?php

namespace App\Filament\Admin\Resources\MaterialCategoryResource\Pages;

use App\Filament\Admin\Resources\MaterialCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialCategory extends EditRecord
{
    protected static string $resource = MaterialCategoryResource::class;

        protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }
}
