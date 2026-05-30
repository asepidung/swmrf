<?php

namespace App\Filament\Resources\LogisticItemResource\Pages;

use App\Filament\Resources\LogisticItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticItems extends ListRecords
{
    protected static string $resource = LogisticItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
