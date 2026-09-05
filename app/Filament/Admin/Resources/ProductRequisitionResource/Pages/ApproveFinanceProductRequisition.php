<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use App\Models\User;

class ApproveFinanceProductRequisition extends EditRecord
{
    protected static string $resource = ProductRequisitionResource::class;
    
    public function getTitle(): string
    {
        return 'Finance Approval: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    /**
     * Halaman keputusan hanya boleh dibuka pada tahapnya sendiri.
     *
     * Tanpa ini, dokumen yang sudah PO Created masih bisa dibuka lewat URL dan
     * di-Approve lagi - PO kedua terbit berikut dokumen uang muka kedua, tanpa
     * error apa pun. Lapis keduanya ada di ProductRequisition::generatePurchaseOrder().
     */
    protected const APPROVABLE_STATUS = 'Pending Finance';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->record->status !== self::APPROVABLE_STATUS) {
            Notification::make()
                ->title(__('This request is no longer waiting for finance approval.'))
                ->body(__('Current status') . ': ' . $this->record->status . '.')
                ->warning()
                ->send();

            $this->redirect(ProductRequisitionResource::getUrl('view', ['record' => $this->record]));
        }
    }

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

    /**
     * Request tanpa satu pun barang tidak boleh menerbitkan PO.
     *
     * Penjagaan harga di atas hanya memeriksa baris yang PUNYA product_id, jadi
     * dokumen yang seluruh barisnya kosong lolos begitu saja dan menghasilkan PO
     * bernilai nol - utang palsu yang mengacaukan perhitungan TOP.
     */
    protected function hasNoItems(): bool
    {
        foreach ($this->data['items'] ?? [] as $item) {
            if (! empty($item['product_id'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nilai tagihan menurut isi FORM saat ini, bukan menurut record tersimpan.
     *
     * Alasannya sama seperti pemeriksaan harga di atas: finance boleh
     * memperbaiki harga di halaman ini, dan batas uang muka harus mengikuti
     * angka yang baru diketik tanpa perlu menyimpan lebih dulu.
     *
     * Perhitungan pajaknya mengikuti ProductRequisition::updateTotalAmount()
     * supaya batasnya sama persis dengan nilai yang kelak menjadi utang.
     */
    protected function currentGrandTotal(): float
    {
        $subtotal = 0.0;

        foreach ($this->data['items'] ?? [] as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $subtotal += ProductRequisitionResource::parseNumber($item['qty'] ?? 0)
                * ProductRequisitionResource::parseNumber($item['price'] ?? 0);
        }

        $supplier = \App\Models\Supplier::find($this->data['supplier_id'] ?? $this->record->supplier_id);

        return ($supplier && $supplier->has_tax) ? $subtotal * 1.11 : $subtotal;
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
            ->modalHeading(__('Approve Request'))
            ->modalDescription(__('Are you sure you want to approve this request and generate the Purchase Order?'))
            ->modalSubmitActionLabel(__('Approve & Generate PO'))
            ->action(function () {
                if ($this->hasNoItems()) {
                    Notification::make()
                        ->title(__('Request has no items, the PO cannot be issued'))
                        ->body(__('Add at least one product, or return the request to purchasing instead.'))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $missing = $this->itemsMissingPrice();

                if ($missing !== []) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('Some prices are still empty, so the purchase order cannot be issued'))
                        ->body(__('A purchase order worth zero creates a fake payable and wrecks the payment-term calculation. These items still have no price') . ': ' . implode(', ', $missing) . '.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                // Argumen KEDUA mematikan toast "Saved" bawaan Filament.
                // Tanpa itu pengguna melihat dua toast sekaligus: "Saved" dari
                // penyimpanan, dan pesan hasil aksinya sendiri.
                $this->save(false, false);

                \Illuminate\Support\Facades\DB::transaction(function () {
                    $this->record->update([
                        'status' => 'PO Created',
                        'reject_note' => null,
                    ]);

                    $this->record->generatePurchaseOrder();
                });
                
                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'review_product_requisitions',
                    __('Beef Request Approved'),
                    __('Request :number has been approved by finance and PO is generated.', ['number' => $this->record->document_number]),
                    \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('view', ['record' => $this->record]),
                    'beef-request-' . $this->record->id,
                    auth()->id(),
                );

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
                    ->label(__('Reason for sending it back'))
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Returned to Purchasing',
                    'reject_note' => $data['reject_note'],
                ]);

                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'review_product_requisitions',
                    __('Beef Request Returned'),
                    __('Request :number has been returned by finance.', ['number' => $this->record->document_number]),
                    \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('review', ['record' => $this->record]),
                    'beef-request-' . $this->record->id,
                    auth()->id(),
                );

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
