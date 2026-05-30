<?php

namespace App\Filament\Resources\BankLedgerResource\Pages;

use App\Filament\Resources\BankLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankLedger extends EditRecord
{
    protected static string $resource = BankLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
