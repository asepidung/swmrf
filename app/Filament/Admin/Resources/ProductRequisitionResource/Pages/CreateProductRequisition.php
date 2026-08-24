<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\User;

class CreateProductRequisition extends CreateRecord
{
    protected static string $resource = ProductRequisitionResource::class;
    
    public array $itemsData = [];

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
            if (!empty($item['product_id'])) {
                // WAJIB di-parse. Input qty dan price kini menampilkan pemisah
                // ribuan ("250.000"), dan bila disimpan mentah akan terbaca 250.
                $qty = ProductRequisitionResource::parseNumber($item['qty'] ?? 0);
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

        \App\Support\TaskNotifier::notifyPermissionHolders(
            'review_product_requisitions',
            __('New Beef Request'),
            __('Request :number is waiting for your review.', ['number' => $this->record->document_number]),
            \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('review', ['record' => $this->record]),
            'beef-request-' . $this->record->id,
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
