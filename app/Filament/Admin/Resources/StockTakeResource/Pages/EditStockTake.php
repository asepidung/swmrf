<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockTake extends EditRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            // Penjagaan yang sama dengan halaman View. Di sini dulu tidak ada
            // penjagaan sama sekali, jadi opname yang sudah dihitung bisa
            // dihapus lewat pintu ini.
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->isDeletable()),
        ];
    }
}
