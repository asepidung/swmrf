<?php

namespace App\Filament\Resources\BeefPurchaseOrderResource\Pages;

use App\Filament\Resources\BeefPurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBeefPurchaseOrder extends EditRecord
{
    protected static string $resource = BeefPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
