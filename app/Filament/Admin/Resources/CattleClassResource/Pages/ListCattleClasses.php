<?php

namespace App\Filament\Admin\Resources\CattleClassResource\Pages;

use App\Filament\Admin\Resources\CattleClassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattleClasses extends ListRecords
{
    protected static string $resource = CattleClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
