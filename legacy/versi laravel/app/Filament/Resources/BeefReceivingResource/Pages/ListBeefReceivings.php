<?php

namespace App\Filament\Resources\BeefReceivingResource\Pages;

use App\Filament\Resources\BeefReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeefReceivings extends ListRecords
{
    protected static string $resource = BeefReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tombol sakti buat buka halaman Draft (PO yang siap di-GR)
            Actions\Action::make('draft')
                ->label('Draft')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(fn() => DraftBeefReceiving::getUrl()),
        ];
    }
}
