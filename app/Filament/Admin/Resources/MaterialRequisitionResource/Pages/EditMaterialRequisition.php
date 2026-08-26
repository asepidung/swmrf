<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Edit Request: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
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
            if (empty($item['material_id'])) {
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
            if (!empty($item['material_id'])) {
                // WAJIB di-parse: input qty dan price kini menampilkan pemisah
                // ribuan ("250.000"), dan bila disimpan mentah akan terbaca 250.
                $qty = MaterialRequisitionResource::parseNumber($item['qty'] ?? 0);
                $price = MaterialRequisitionResource::parseNumber($item['price'] ?? 0);

                $this->record->items()->create([
                    'material_id' => $item['material_id'],
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
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
