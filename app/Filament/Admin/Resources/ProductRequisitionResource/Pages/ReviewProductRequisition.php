<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Models\User;

class ReviewProductRequisition extends EditRecord
{
    protected static string $resource = ProductRequisitionResource::class;
    
    public function getTitle(): string
    {
        return 'Review Request: ' . $this->record->document_number;
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
            ->label('Approve (Send to Finance)')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->requiresConfirmation()
            ->action(function () {
                $missing = $this->itemsMissingPrice();

                if ($missing !== []) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('Harga belum lengkap, belum bisa diteruskan ke Finance'))
                        ->body(__('Isi dulu harga untuk barang berikut') . ': ' . implode(', ', $missing) . '.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $this->save(false);
                
                $this->record->update([
                    'status' => 'Pending Finance',
                    'reject_note' => null,
                ]);

                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'approve_product_requisitions',
                    __('Beef Request Awaiting Approval'),
                    __('Request :number has been priced by purchasing and needs your approval.', ['number' => $this->record->document_number]),
                    \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('approve-finance', ['record' => $this->record]),
                    'beef-request-' . $this->record->id,
                    auth()->id(),
                );

                // Notifications rely on PendingTaskWidget now.
                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Reject / Return')
            ->color('danger')
            ->icon('heroicon-s-x-circle')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reject_note')
                    ->label('Alasan Penolakan/Revisi')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Rejected',
                    'reject_note' => $data['reject_note'],
                ]);

                // User can see rejected status in their list.
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
