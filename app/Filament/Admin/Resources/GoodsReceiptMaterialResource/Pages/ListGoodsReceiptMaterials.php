<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoodsReceiptMaterials extends ListRecords
{
    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::$resource::getUrl('detail-list')),
            Actions\Action::make('draft_po')
                ->label(__('Draft PO'))
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(fn () => GoodsReceiptMaterialResource::getUrl('drafts')),
        ];
    }
}
