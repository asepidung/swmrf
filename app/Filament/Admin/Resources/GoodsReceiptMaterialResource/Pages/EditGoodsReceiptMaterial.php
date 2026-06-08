<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceiptMaterial extends EditRecord
{
    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
