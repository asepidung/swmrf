<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoodsReceiptProducts extends ListRecords
{
    protected static string $resource = GoodsReceiptProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::$resource::getUrl('detail-list')),
            Actions\Action::make('create')
                ->label(__('Create'))
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(fn () => GoodsReceiptProductResource::getUrl('drafts')),
        ];
    }
}
