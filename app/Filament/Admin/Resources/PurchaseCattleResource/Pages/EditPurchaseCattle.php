<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use App\Models\PurchaseCattle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPurchaseCattle extends EditRecord
{
    protected static string $resource = PurchaseCattleResource::class;

    /**
     * CR-02 (keputusan Owner): PO yang sudah punya penerimaan terkunci penuh DI SERVER. Menyembunyikan tombol Simpan
     * tidak cukup, karena state form yang dikirim paksa tetap tersimpan; statusnya dibaca ulang dengan baris terkunci.
     */
    protected function beforeSave(): void
    {
        $po = PurchaseCattle::whereKey($this->record->id)->lockForUpdate()->first();

        if ($po && $po->receivings()->exists()) {
            Notification::make()
                ->title(__('This purchase order already has a receiving and cannot be edited.'))
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['cattle_class_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('po-cattle.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => $record->receivings()->exists()),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->getRecord()->receivings()->exists()) {
            return [];
        }
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
