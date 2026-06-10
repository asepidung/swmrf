<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRepack extends CreateRecord
{
    protected static string $resource = RepackResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
