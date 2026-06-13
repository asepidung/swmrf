<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected array $itemsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        foreach ($this->itemsData as $item) {
            $record->items()->create([
                'product_id' => $item['product_id'],
                'weight' => (int) str_replace('.', '', $item['weight'] ?? 0),
                'price' => (int) str_replace('.', '', $item['price'] ?? 0),
                'discount' => (int) str_replace('.', '', $item['discount'] ?? 0),
                'note' => $item['note'] ?? '',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
