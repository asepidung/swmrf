<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialRequisition extends ViewRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
