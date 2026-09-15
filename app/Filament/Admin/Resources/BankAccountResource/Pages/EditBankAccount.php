<?php

namespace App\Filament\Admin\Resources\BankAccountResource\Pages;

use App\Filament\Admin\Resources\BankAccountResource;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // bank_transactions.bank_account_id RESTRICT -- datanya aman,
            // tapi tanpa ini mencoba hapus rekening yang sudah punya mutasi
            // menampilkan galat SQL mentah.
            Actions\DeleteAction::make()
                ->action(function () {
                    $record = $this->getRecord();

                    if (MasterDataDeletion::attempt(fn () => $record->delete(), __('Bank Account').' '.$record->initial)) {
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
