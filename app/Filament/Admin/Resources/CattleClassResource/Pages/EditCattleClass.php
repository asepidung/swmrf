<?php

namespace App\Filament\Admin\Resources\CattleClassResource\Pages;

use App\Filament\Admin\Resources\CattleClassResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCattleClass extends EditRecord
{
    protected static string $resource = CattleClassResource::class;

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
