<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialRequisitions extends ListRecords
{
    protected static string $resource = MaterialRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail')
                ->label('Detail')
                ->icon('heroicon-o-list-bullet')
                ->url(static::getResource()::getUrl('detail-list')),
            Actions\CreateAction::make()
                ->label('Create'),
        ];
    }
}
