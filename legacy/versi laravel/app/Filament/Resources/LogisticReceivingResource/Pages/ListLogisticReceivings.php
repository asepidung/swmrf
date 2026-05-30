<?php

namespace App\Filament\Resources\LogisticReceivingResource\Pages;

use App\Filament\Resources\LogisticReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticReceivings extends ListRecords
{
    protected static string $resource = LogisticReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Cuma ada 1 tombol di pojok kanan atas: DRAFT
            Actions\Action::make('draft')
                ->label('Draft')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                // Mengarahkan langsung ke class halaman Draft lu
                ->url(fn() => DraftLogisticReceiving::getUrl()),
        ];
    }
}
