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

    /**
     * Halaman keputusan hanya boleh dibuka pada tahapnya sendiri.
     *
     * Tombol di halaman View memang sudah disembunyikan menurut status, tapi
     * halamannya sendiri tetap bisa dicapai dengan mengetik URL - dan dulu
     * tidak ada satu pun pemeriksaan status di sini.
     */
    protected const EDITABLE_STATUSES = ['Requested', 'Returned to Purchasing'];

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (! in_array($this->record->status, self::EDITABLE_STATUSES, true)) {
            Notification::make()
                ->title(__('This request is no longer at the purchasing review stage.'))
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
     * Request tanpa satu pun barang tidak boleh maju.
     *
     * Penjagaan harga di bawah hanya memeriksa baris yang PUNYA product_id.
     * Bila seluruh baris dikosongkan, daftar "harga kosong" ikut kosong dan
     * dokumen dianggap lulus - lalu naik ke Finance dengan 0 item dan total 0,
     * persis PO bernilai nol yang penjagaan itu dibangun untuk mencegahnya.
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
                if ($this->hasNoItems()) {
                    Notification::make()
                        ->title(__('Request has no items, it cannot be sent to Finance'))
                        ->body(__('Add at least one product, or reject the request instead.'))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

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

                // Argumen KEDUA mematikan toast "Saved" bawaan Filament.
                // Tanpa itu pengguna melihat dua toast sekaligus: "Saved" dari
                // penyimpanan, dan pesan hasil aksinya sendiri.
                $this->save(false, false);
                
                $this->record->update([
                    'status' => 'Pending Finance',
                    'reject_note' => null,
                    'reviewed_by' => auth()->id(),
                ]);

                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'approve_product_requisitions',
                    __('Beef Request Awaiting Approval'),
                    __('Request :number has been priced by purchasing and needs your approval.', ['number' => $this->record->document_number]),
                    \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('approve-finance', ['record' => $this->record]),
                    'beef-request-' . $this->record->id,
                    auth()->id(),
                );

                if ($this->record->user) {
                    \App\Support\TaskNotifier::notifyUser(
                        $this->record->user,
                        __('Beef Request Approved'),
                        __('Your request :number was approved by purchasing and sent to finance.', ['number' => $this->record->document_number]),
                        \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('view', ['record' => $this->record]),
                        'beef-request-' . $this->record->id,
                    );
                }

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

                if ($this->record->user) {
                    \App\Support\TaskNotifier::notifyUser(
                        $this->record->user,
                        __('Beef Request Rejected'),
                        __('Your request :number has been rejected by purchasing.', ['number' => $this->record->document_number]),
                        \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('view', ['record' => $this->record]),
                        'beef-request-' . $this->record->id,
                    );
                }

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
