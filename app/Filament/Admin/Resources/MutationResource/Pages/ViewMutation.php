<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMutation extends ViewRecord
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan')
                ->label('Scan Barang')
                ->hiddenLabel()
                ->tooltip('Scan Barang')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn ($record) => MutationResource::getUrl('scan', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\Action::make('print')
                ->label('Cetak Laporan')
                ->hiddenLabel()
                ->tooltip('Cetak Laporan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record) => route('filament.admin.resources.mutations.print', ['record' => $record]))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->hiddenLabel()
                ->tooltip('Edit')
                ->icon('heroicon-o-pencil-square'),

            Actions\DeleteAction::make()
                ->hiddenLabel()
                ->tooltip('Delete')
                ->icon('heroicon-o-trash')
                ->visible(fn ($record) => $record->items()->count() === 0),
        ];
    }
}
