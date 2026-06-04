<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->map(function ($item) {
            return [
                'material_id' => $item->material_id,
                'qty' => $item->qty,
                'price' => $item->price,
                'item_total' => number_format($item->qty * $item->price, 0, ',', '.'),
                'note' => $item->note,
            ];
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
                $this->record->items()->create([
                    'material_id' => $item['material_id'],
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
