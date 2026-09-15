<?php

namespace App\Filament\Admin\Resources\SupplierResource\Pages;

use App\Filament\Admin\Resources\SupplierResource;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Notifications\Notification;
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
            // Tombolnya disembunyikan saat supplier masih dipakai (pola sama
            // EditCustomer/EditCustomerGroup), DAN ditolak ulang di sini --
            // lihat Supplier::isInUse().
            Actions\DeleteAction::make()
                ->hidden(fn ($record) => $record->isInUse())
                ->action(function () {
                    $record = $this->getRecord();

                    if ($record->isInUse()) {
                        Notification::make()
                            ->title(__('This supplier cannot be deleted'))
                            ->body(__('It still has cattle receivings or payments recorded against it.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    if (MasterDataDeletion::attempt(fn () => $record->delete(), __('Supplier').' '.$record->name)) {
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
