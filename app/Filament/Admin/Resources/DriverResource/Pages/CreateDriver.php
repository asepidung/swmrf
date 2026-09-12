<?php

namespace App\Filament\Admin\Resources\DriverResource\Pages;

use App\Filament\Admin\Resources\DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    protected static string $resource = DriverResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
