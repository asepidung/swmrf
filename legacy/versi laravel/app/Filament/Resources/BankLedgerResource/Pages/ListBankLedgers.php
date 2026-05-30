<?php

namespace App\Filament\Resources\BankLedgerResource\Pages;

use App\Filament\Resources\BankLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankLedgers extends ListRecords
{
    protected static string $resource = BankLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
