<?php

namespace App\Filament\Resources\CattleReceivingResource\Pages;

use App\Filament\Resources\CattleReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCattleReceivings extends ListRecords
{
    protected static string $resource = CattleReceivingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Ganti CreateAction bawaan dengan tombol Draft
            Actions\Action::make('draft')
                ->label('Draft / Select PO')
                ->icon('heroicon-o-document-plus')
                ->color('warning') // Biar warnanya beda dan mencolok
                ->url(CattleReceivingResource::getUrl('draft')),
        ];
    }
}
