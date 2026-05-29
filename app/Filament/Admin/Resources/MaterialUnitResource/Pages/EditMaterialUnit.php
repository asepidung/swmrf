<?php

namespace App\Filament\Admin\Resources\MaterialUnitResource\Pages;

use App\Filament\Admin\Resources\MaterialUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialUnit extends EditRecord
{
    protected static string $resource = MaterialUnitResource::class;

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
