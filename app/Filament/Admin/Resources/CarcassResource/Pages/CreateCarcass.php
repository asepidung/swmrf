<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCarcass extends CreateRecord
{
    protected static string $resource = CarcassResource::class;



    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
