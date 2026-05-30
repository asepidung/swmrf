<?php

namespace App\Filament\Resources\CattlePurchaseOrderResource\Pages;

use App\Filament\Resources\CattlePurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditCattlePurchaseOrder extends EditRecord
{
    protected static string $resource = CattlePurchaseOrderResource::class;

    // PROTEKSI URL TEMBAK LANGSUNG
    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Kalau PO sudah punya data receiving, tendang balik ke View!
        if ($this->record->receivings()->exists()) {
            Notification::make()
                ->warning()
                ->title('Akses Ditolak')
                ->body('PO Cattle ini sudah diterima (GRC). Data tidak dapat diedit lagi.')
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record->id]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
