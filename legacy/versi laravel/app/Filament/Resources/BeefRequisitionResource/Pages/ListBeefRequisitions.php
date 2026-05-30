<?php

namespace App\Filament\Resources\BeefRequisitionResource\Pages;

use App\Filament\Resources\BeefRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeefRequisitions extends ListRecords
{
    protected static string $resource = BeefRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
