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

        $approvers = \App\Models\User::where('role', 'programmer')
            ->orWhereHas('permissions', function ($q) {
                $q->where('name', 'review_material_requisitions');
            })->get();

        if ($approvers->count() > 0) {
            \Filament\Notifications\Notification::make()
                ->title('New Material Request')
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
