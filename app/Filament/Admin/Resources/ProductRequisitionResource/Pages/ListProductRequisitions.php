<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductRequisitions extends ListRecords
{
    protected static string $resource = ProductRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Actions\CreateAction::make()->label(__('Create')),
        ];
    }
}
