<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn(): string => route('print.salesorder', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $items = [];
        foreach ($this->record->items as $item) {
            $items['item_' . \Illuminate\Support\Str::random(12)] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'weight' => number_format($item->weight, 0, '', '.'),
                'price' => number_format($item->price, 0, '', '.'),
                'discount' => number_format($item->discount, 0, '', '.'),
                'note' => $item->note,
            ];
        }
        $data['items'] = $items;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $record->items()->delete();
        
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
