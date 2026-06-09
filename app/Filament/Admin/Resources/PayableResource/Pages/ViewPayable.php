<?php

namespace App\Filament\Admin\Resources\PayableResource\Pages;

use App\Filament\Admin\Resources\PayableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayable extends ViewRecord
{
    protected static string $resource = PayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
        ];
    }
}
