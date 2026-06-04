<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Models\MaterialRequisitionItem;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing items into the form
        $data['items'] = $this->record->items->map(fn($item) => [
            'material_id' => $item->material_id,
            'qty'         => $item->qty,
            'price'       => $item->price,
            'note'        => $item->note,
        ])->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $items = $this->data['items'] ?? [];

        // Delete old items and recreate (simplest sync approach)
        $this->record->items()->delete();

        foreach ($items as $item) {
            if (empty($item['material_id'])) continue;

            $qty   = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            MaterialRequisitionItem::create([
                'material_requisition_id' => $this->record->id,
                'material_id'             => $item['material_id'],
                'qty'                     => $qty,
                'price'                   => $price,
                'subtotal'                => $qty * $price,
                'note'                    => $item['note'] ?? null,
            ]);
        }

        $this->record->updateTotalAmount();
    }
}
