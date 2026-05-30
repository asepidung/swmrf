<?php

namespace App\Filament\Resources\BoningResource\Pages;

use App\Filament\Resources\BoningResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBoning extends CreateRecord
{
    protected static string $resource = BoningResource::class;

    // 4. Override tombol bawaan Filament (cuma nampilin Create dan Cancel)
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    // Biar kalau habis klik Save, dia langsung balik ke halaman list tabel
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
