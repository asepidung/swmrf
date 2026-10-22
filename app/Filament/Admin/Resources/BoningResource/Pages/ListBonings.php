<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBonings extends ListRecords
{
    protected static string $resource = BoningResource::class;

    /**
     * TIDAK ADA aksi "Batas Susut" di sini, berbeda dengan Repack.
     *
     * Susut boning sengaja tidak dihitung -- lihat catatan panjangnya di
     * `Boning`. Menyediakan tempat mengisi ambang untuk angka yang tidak
     * pernah dihitung hanya membuat orang mengira ada yang sedang dijaga.
     */
    protected function getHeaderActions(): array
    {
        return [
            // Boning dibuat dari Draft Boning (karkas yang menunggu, dipilih beberapa sekaligus), bukan dari form kosong.
            Actions\Action::make('draft')
                ->label(__('Draft Boning'))
                ->icon('heroicon-m-clipboard-document-list')
                ->color('info')
                ->visible(fn (): bool => auth()->user()?->hasPermission('create_bonings') ?? false)
                ->url(fn (): string => $this->getResource()::getUrl('draft')),
        ];
    }
}
