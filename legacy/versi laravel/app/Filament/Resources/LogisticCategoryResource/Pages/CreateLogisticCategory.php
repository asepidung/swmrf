<?php

namespace App\Filament\Resources\LogisticCategoryResource\Pages;

use App\Filament\Resources\LogisticCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLogisticCategory extends CreateRecord
{
    protected static string $resource = LogisticCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
