<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use App\Models\SalesOrder;
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
                ->openUrlInNewTab()
                ->visible(fn(): bool => !$this->record->status === SalesOrder::STATUS_CANCELLED),
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->hidden(fn(): bool => in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)),
            Actions\ForceDeleteAction::make()
                ->hidden(fn(): bool => in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)),
            Actions\RestoreAction::make()
                ->hidden(fn(): bool => in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)),
        ];
    }

    protected function getFormActions(): array
    {
        if (in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)) {
            return [];
        }
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
                // Berat dan harga memang berpemisah ribuan -- titiknya
                // dibuang di sini karena JavaScript di form yang memasangnya.
                //
                // Diskon TIDAK. Form sengaja tidak memformat kolom itu, jadi
                // titik di sana hanya mungkin berarti koma desimal, dan
                // membuangnya bukan membulatkan melainkan mengubah artinya:
                // 2,5% menjadi 25%, 12,75% menjadi 1275%. Validasi tidak
                // menangkapnya karena perusakannya terjadi SESUDAH validasi.
                //
                // Diskon kini persen bulat, jadi dibaca apa adanya.
                'weight' => (int) str_replace('.', '', $item['weight'] ?? 0),
                'price' => (int) str_replace('.', '', $item['price'] ?? 0),
                'discount' => (int) ($item['discount'] ?? 0),
                'note' => $item['note'] ?? '',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
