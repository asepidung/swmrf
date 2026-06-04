<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Models\MaterialRequisitionItem;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialRequisition extends CreateRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    protected function afterCreate(): void
    {
        $items = $this->data['items'] ?? [];

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
