<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialStockTakes extends ListRecords
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Lewat __(). Penjaga hardcode hanya mencari teks berbahasa
            // INDONESIA, jadi teks Inggris yang tidak diterjemahkan lolos --
            // dan pengguna berbahasa Indonesia membacanya apa adanya.
            Actions\CreateAction::make()->label(__('Create')),
        ];
    }
}
