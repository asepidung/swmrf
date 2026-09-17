<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use App\Models\ProductRequisition;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditProductRequisition extends EditRecord
{
    protected static string $resource = ProductRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Edit Request: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    /**
     * Kembar dengan EditMaterialRequisition -- lihat penjelasan di sana.
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status !== 'Requested') {
            Notification::make()
                ->title(__('This request can no longer be edited because it has moved past the Requested stage.'))
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function beforeSave(): void
    {
        $locked = ProductRequisition::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && $locked->status !== 'Requested') {
            Notification::make()
                ->title(__('This request can no longer be edited because it has moved past the Requested stage.'))
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'product_id' => $item->product_id,
                'qty' => (int) $item->qty,
                'price' => (float) $item->price,
                'item_total' => (float) ($item->qty * $item->price),
                'note' => $item->note,
            ]];
        })->toArray();
        return $data;
    }

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['product_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->items()->delete();
        foreach ($this->itemsData as $item) {
            if (!empty($item['product_id'])) {
                // WAJIB di-parse. Input qty dan price kini menampilkan pemisah
                // ribuan ("250.000"), dan bila disimpan mentah akan terbaca 250.
                $qty = (int) round(ProductRequisitionResource::parseNumber($item['qty'] ?? 0));
                $price = ProductRequisitionResource::parseNumber($item['price'] ?? 0);

                $this->record->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $qty * $price,
                    'note' => $item['note'] ?? null,
                ]);
            }
        }
        $this->record->updateTotalAmount();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            // Tombolnya disembunyikan begitu PO sudah terbit -- penjagaan
            // sungguhan ada di ProductRequisition::deleting(), ini cuma
            // mencegah orang menabraknya lewat jalur normal.
            Actions\DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->purchaseProduct()->exists()),
            Actions\RestoreAction::make(),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
