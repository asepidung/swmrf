<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\User;

class CreateMaterialRequisition extends CreateRecord
{
    protected static string $resource = MaterialRequisitionResource::class;
    
    public array $itemsData = [];

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['total_amount'] = 0;
        $data['tax_amount'] = 0;
        
        // Save items data to a property and unset to avoid DB constraint issues
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
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

        \App\Support\TaskNotifier::notifyPermissionHolders(
            'review_material_requisitions',
            __('New Material Request'),
            // Sengaja pendek dan tanpa nomor dokumen: di layar HP judul dan isi
            // sama-sama terpotong bila panjang.
            __('Waiting for your review.'),
            \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('review', ['record' => $this->record]),
            'material-request-' . $this->record->id,
            auth()->id(),
        );
    }


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
}
