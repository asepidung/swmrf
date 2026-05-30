<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    // Tambahkan ini juga biar abis edit langsung balik ke tabel
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
