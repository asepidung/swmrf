<?php

namespace App\Filament\Admin\Resources\MaterialAdjustmentResource\Pages;

use App\Filament\Admin\Resources\MaterialAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialAdjustment extends CreateRecord
{
    protected static string $resource = MaterialAdjustmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
