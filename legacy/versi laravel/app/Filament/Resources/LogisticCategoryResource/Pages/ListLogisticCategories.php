<?php

namespace App\Filament\Resources\LogisticCategoryResource\Pages;

use App\Filament\Resources\LogisticCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticCategories extends ListRecords
{
    protected static string $resource = LogisticCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
