<?php

namespace App\Filament\Admin\Resources\FinancialLossResource\Pages;

use App\Filament\Admin\Resources\FinancialLossResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialLosses extends ListRecords
{
    protected static string $resource = FinancialLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Read-only module, no create action
        ];
    }
}
