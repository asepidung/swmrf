<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGoodsReceiptMaterial extends ViewRecord
{
    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
