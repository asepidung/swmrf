<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMutations extends ListRecords
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::getResource()::getUrl('detail-list')),
        ];
    }
}
