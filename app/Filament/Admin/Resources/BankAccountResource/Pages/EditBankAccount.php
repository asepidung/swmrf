<?php

namespace App\Filament\Admin\Resources\BankAccountResource\Pages;

use App\Filament\Admin\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sebelumnya DeleteAction polos -- galat SQL mentah kalau
            // akun banknya masih dipakai. Sama seperti EditWarehouse.
            Actions\DeleteAction::make()
                ->action(function () {
                    if (\App\Support\MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Bank Account').' '.($this->record->bank_name ?? $this->record->initial),
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
}
