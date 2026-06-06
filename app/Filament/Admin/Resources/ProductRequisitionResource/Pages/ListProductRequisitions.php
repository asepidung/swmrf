<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductRequisitions extends ListRecords
{
    protected static string $resource = ProductRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detailList')
                ->label(__('Detail'))
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->url(fn (): string => ProductRequisitionResource::getUrl('detail-list')),
            Actions\CreateAction::make(),
        ];
    }
}
