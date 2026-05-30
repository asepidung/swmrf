<?php

namespace App\Filament\Resources\CompanyBankResource\Pages;

use App\Filament\Resources\CompanyBankResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanyBanks extends ListRecords
{
    protected static string $resource = CompanyBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
