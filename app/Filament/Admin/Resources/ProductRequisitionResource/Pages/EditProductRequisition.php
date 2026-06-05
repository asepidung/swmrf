<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductRequisition extends EditRecord
{
    protected static string $resource = ProductRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Edit Request: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'product_id' => $item->product_id,
                'qty' => (float) $item->qty,
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
                $this->record->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'subtotal' => ($item['qty'] ?? 0) * ($item['price'] ?? 0),
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
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
