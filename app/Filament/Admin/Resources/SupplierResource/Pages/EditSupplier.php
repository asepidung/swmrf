<?php

namespace App\Filament\Admin\Resources\SupplierResource\Pages;

use App\Filament\Admin\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

        protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(fn() => __('Back'))
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
            // Sebelumnya DeleteAction polos -- galat SQL mentah kalau
            // supplier-nya masih dipakai. Sama seperti EditWarehouse.
            Actions\DeleteAction::make()
                ->action(function () {
                    if (\App\Support\MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Supplier').' '.$this->record->name,
                    )) {
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
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
