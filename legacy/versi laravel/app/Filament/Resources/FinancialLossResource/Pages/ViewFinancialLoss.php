<?php

namespace App\Filament\Resources\FinancialLossResource\Pages;

use App\Filament\Resources\FinancialLossResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFinancialLoss extends ViewRecord
{
    protected static string $resource = FinancialLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
