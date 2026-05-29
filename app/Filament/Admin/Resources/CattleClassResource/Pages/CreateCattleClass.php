<?php

namespace App\Filament\Admin\Resources\CattleClassResource\Pages;

use App\Filament\Admin\Resources\CattleClassResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCattleClass extends CreateRecord
{
    protected static string $resource = CattleClassResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
