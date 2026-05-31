<?php

namespace App\Filament\Admin\Resources\FinancialLossResource\Pages;

use App\Filament\Admin\Resources\FinancialLossResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFinancialLoss extends ViewRecord
{
    protected static string $resource = FinancialLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
        ];
    }
}
