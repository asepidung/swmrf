<?php

namespace App\Filament\Resources\BeefReceivingResource\Pages;

use App\Filament\Resources\BeefReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBeefReceiving extends EditRecord
{
    protected static string $resource = BeefReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
