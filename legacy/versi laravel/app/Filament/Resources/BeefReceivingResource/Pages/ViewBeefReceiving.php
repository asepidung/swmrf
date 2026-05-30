<?php

namespace App\Filament\Resources\BeefReceivingResource\Pages;

use App\Filament\Resources\BeefReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBeefReceiving extends ViewRecord
{
    protected static string $resource = BeefReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
