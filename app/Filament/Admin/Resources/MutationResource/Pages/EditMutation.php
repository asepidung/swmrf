<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMutation extends EditRecord
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan')
                ->label(__('Scan Goods'))
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn ($record) => MutationResource::getUrl('scan', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\ViewAction::make(),
            // Aturan yang sama dengan ViewMutation: hanya DRAFT yang KOSONG
            // yang boleh dihapus. Sebelumnya halaman ini sama sekali tidak
            // menjaga apa pun -- DRAFT yang barangnya sudah discan (stok
            // sudah berkurang dari gudang asal) bisa ikut dihapus lewat
            // sini, dan baris MutationItem-nya jadi yatim piatu (soft
            // delete tidak ikut cascade).
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === 'DRAFT' && $record->items()->count() === 0),
        ];
    }
}
