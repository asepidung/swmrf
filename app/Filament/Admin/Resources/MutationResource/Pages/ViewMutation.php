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
            Actions\Action::make('receive')
                ->label('Terima Mutasi')
                ->hiddenLabel()
                ->tooltip('Terima Mutasi')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->url(fn ($record) => MutationResource::getUrl('receive', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'SENT'),

            Actions\Action::make('print')
                ->label('Cetak Laporan')
                ->hiddenLabel()
                ->tooltip('Cetak Laporan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record) => route('filament.admin.resources.mutations.print', ['record' => $record]))
                ->openUrlInNewTab(),

            Actions\Action::make('scan')
                ->label('Scan Barang')
                ->hiddenLabel()
                ->tooltip('Scan Barang')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn ($record) => MutationResource::getUrl('scan', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\EditAction::make()
                ->hiddenLabel()
                ->tooltip('Edit')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\DeleteAction::make()
                ->hiddenLabel()
                ->tooltip('Delete')
                ->icon('heroicon-o-trash')
                ->visible(fn ($record) => $record->status === 'DRAFT' && $record->items()->count() === 0),
                
            Actions\Action::make('back')
                ->label('Kembali')
                ->hiddenLabel()
                ->tooltip('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MutationResource::getUrl('index')),
        ];
    }
}
