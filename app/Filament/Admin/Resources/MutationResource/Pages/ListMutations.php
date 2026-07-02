<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMutations extends ListRecords
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
