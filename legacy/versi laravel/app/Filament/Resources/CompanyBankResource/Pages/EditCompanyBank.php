<?php

namespace App\Filament\Resources\CompanyBankResource\Pages;

use App\Filament\Resources\CompanyBankResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompanyBank extends EditRecord
{
    protected static string $resource = CompanyBankResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
