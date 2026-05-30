<?php

namespace App\Filament\Resources\LogisticRequisitionResource\Pages;

use App\Filament\Resources\LogisticRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticRequisitions extends ListRecords
{
    protected static string $resource = LogisticRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
