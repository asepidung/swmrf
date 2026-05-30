<?php

namespace App\Filament\Resources\CattlePurchaseOrderResource\Pages;

use App\Filament\Resources\CattlePurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCattlePurchaseOrder extends ViewRecord
{
    protected static string $resource = CattlePurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tombol Kembali
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),

            // Tombol Print
            Actions\Action::make('print')
                ->tooltip('Print GRC')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->iconButton()
                ->url(fn($record) => route('print.cattle-po', $record->id))
                ->openUrlInNewTab(),

            // Tombol Edit
            Actions\EditAction::make()
                ->tooltip('Edit GRC')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->iconButton(),

            Actions\DeleteAction::make()
                ->tooltip('Hapus GRC')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->iconButton(),
        ];
    }
}
