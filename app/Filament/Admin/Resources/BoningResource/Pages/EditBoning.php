<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoning extends EditRecord
{
    protected static string $resource = BoningResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->kunci, 403, 'Data has been locked.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->getRecord()->kunci == 1 || $this->getRecord()->items()->exists()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->hidden(fn () => $this->getRecord()->kunci == 1 || $this->getRecord()->items()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
