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

        $approvers = \App\Models\User::where('role', 'programmer')
            ->orWhereHas('permissions', function ($q) {
                $q->where('name', 'review_product_requisitions');
            })->get();

        if ($approvers->count() > 0) {
            \Filament\Notifications\Notification::make()
                ->title('New Beef Request')
                ->body('A new request was submitted by ' . (\Illuminate\Support\Facades\Auth::user()->name ?? 'User') . ' and requires your review.')
                ->info()
                ->sendToDatabase($approvers)
                ->broadcast($approvers);
        }
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
