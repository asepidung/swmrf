<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturnPlans extends ListRecords
{
    protected static string $resource = SalesReturnPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
