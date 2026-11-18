<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockTake extends EditRecord
{
    protected static string $resource = StockTakeResource::class;

    /**
     * Periode, tanggal, dan catatan opname tidak boleh berubah sesudah opname selesai atau dibatalkan. Tab yang dibuka
     * sebelum itu masih bisa menekan simpan, jadi statusnya dibaca ulang dari basis data (audit pra-rilis, ED-02).
     */
    protected function beforeSave(): void
    {
        $status = \App\Models\StockTake::whereKey($this->getRecord()->getKey())->value('status');

        if (! in_array($status, \App\Models\StockTake::STATUS_SEDANG_MENGHITUNG, true)) {
            \Filament\Notifications\Notification::make()
                ->title(__('This stock count is no longer being counted, so it cannot be changed.'))
                ->danger()
                ->send();

            $this->halt();
        }
    }

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
