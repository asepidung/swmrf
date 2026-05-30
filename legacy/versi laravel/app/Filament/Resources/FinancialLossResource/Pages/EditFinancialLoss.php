<?php

namespace App\Filament\Resources\FinancialLossResource\Pages;

use App\Filament\Resources\FinancialLossResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinancialLoss extends EditRecord
{
    protected static string $resource = FinancialLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
