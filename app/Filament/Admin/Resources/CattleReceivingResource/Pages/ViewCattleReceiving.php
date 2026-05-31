<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCattleReceiving extends ViewRecord
{
    protected static string $resource = CattleReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->url(fn() => static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}
