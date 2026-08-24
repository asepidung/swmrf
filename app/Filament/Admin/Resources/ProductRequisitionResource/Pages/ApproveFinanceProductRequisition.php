<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\User;

class ApproveFinanceProductRequisition extends EditRecord
{
    protected static string $resource = ProductRequisitionResource::class;
    
    public function getTitle(): string
    {
        return 'Finance Approval: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'product_id' => $item->product_id,
                'qty' => number_format((float) $item->qty, 2, ',', '.'),
                'price' => number_format((float) $item->price, 0, ',', '.'),
                'item_total' => (float) ($item->qty * $item->price),
                'note' => $item->note,
            ]];
        })->toArray();
        return $data;
    }

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    /**
     * Barang tanpa harga tidak boleh lolos.
     *
     * Keputusan Project Owner: purchasing-lah yang mengisi harga, karena
     * dialah yang tahu harga supplier. Pemohon (gudang/produksi) tidak
     * dipantulkan bolak-balik hanya karena harga kosong.
     *
     * Yang diperiksa adalah isi FORM, bukan record di database, supaya harga
     * yang baru saja diketik purchasing langsung terhitung tanpa perlu
     * menyimpan lebih dulu.
     *
     * @return array<int, string> nama barang yang harganya masih kosong
     */
    protected function itemsMissingPrice(): array
    {
        $missing = [];

        foreach ($this->data['items'] ?? [] as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            if (ProductRequisitionResource::parseNumber($item['price'] ?? 0) > 0) {
                continue;
            }

            $product = \App\Models\Product::find($item['product_id']);
            $missing[] = $product->name ?? ('#' . $item['product_id']);
        }

        return $missing;
    }

    protected function afterSave(): void
    {
        $this->record->items()->delete();
        foreach ($this->itemsData as $item) {
            if (!empty($item['product_id'])) {
                $qty = ProductRequisitionResource::parseNumber($item['qty'] ?? 0);
                $price = ProductRequisitionResource::parseNumber($item['price'] ?? 0);

                $this->record->items()->create([
                    'product_id' => $item['product_id'],
                    // WAJIB di-parse: input menampilkan pemisah ribuan ("250.000"),
                    // yang bila disimpan mentah akan terbaca 250.
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $qty * $price,
                    'note' => $item['note'] ?? null,
                ]);
            }
        }
        $this->record->updateTotalAmount();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getApproveAction(),
            $this->getRejectAction(),
        ];
    }

    protected function getApproveAction(): Actions\Action
    {
        return Actions\Action::make('approve')
            ->label('Approve & Generate PO')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->requiresConfirmation()
            ->action(function () {
                $missing = $this->itemsMissingPrice();

                if ($missing !== []) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('Harga belum lengkap, PO belum bisa diterbitkan'))
                        ->body(__('PO bernilai nol akan menciptakan utang palsu dan mengacaukan perhitungan TOP. Barang yang harganya masih kosong') . ': ' . implode(', ', $missing) . '.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $this->save(false); // Make sure to save any changes made by Finance, if any

                \Illuminate\Support\Facades\DB::transaction(function () {
                    $this->record->update([
                        'status' => 'PO Created',
                        'reject_note' => null,
                    ]);
                    
                    $this->record->generatePurchaseOrder();
                });
                
                // Notifications rely on PendingTaskWidget now.
                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Return to Purchasing')
            ->color('danger')
            ->icon('heroicon-s-arrow-uturn-left')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reject_note')
                    ->label('Alasan Pengembalian')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Returned to Purchasing',
                    'reject_note' => $data['reject_note'],
                ]);

                // Notifications rely on PendingTaskWidget now.
                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
