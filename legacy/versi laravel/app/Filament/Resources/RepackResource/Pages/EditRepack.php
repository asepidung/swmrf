<?php

namespace App\Filament\Resources\RepackResource\Pages;

use App\Filament\Resources\RepackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRepack extends EditRecord
{
    protected static string $resource = RepackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
