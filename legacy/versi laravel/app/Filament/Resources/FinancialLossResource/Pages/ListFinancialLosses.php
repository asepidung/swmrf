<?php

namespace App\Filament\Resources\FinancialLossResource\Pages;

use App\Filament\Resources\FinancialLossResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialLosses extends ListRecords
{
    protected static string $resource = FinancialLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
