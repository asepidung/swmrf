<?php

namespace App\Filament\Resources\BeefRequisitionResource\Pages;

use App\Filament\Resources\BeefRequisitionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewBeefRequisition extends ViewRecord
{
    protected static string $resource = BeefRequisitionResource::class;

    // Menambahkan tombol kembali ke tabel
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url(fn() => $this->getResource()::getUrl('index')),
        ];
    }
}
