<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\User;
use Filament\Support\RawJs;

class ApproveFinanceMaterialRequisition extends EditRecord
{
    use \App\Filament\Concerns\GuardsRequisitionStage;

    /**
     * Halaman ini TIDAK boleh terbuka lewat alamatnya saja.
     *
     * Izinnya sudah ada dan sudah diperiksa -- tetapi hanya untuk menentukan
     * apakah TAUTANNYA ditampilkan. Halamannya sendiri tidak memeriksa apa
     * pun, jadi siapa pun yang boleh melihat daftar permintaan bisa
     * membukanya langsung dan menekan tombolnya.
     *
     * Sudah dibuktikan sebelum diperbaiki: pengguna dengan hanya
     * `view_product_requisitions` -- tanpa izin persetujuan apa pun --
     * mengembalikan `true` untuk kedua halaman ini.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isProgrammer()
            || (auth()->user()?->hasPermission('approve_material_requisitions') ?? false);
    }

    protected static string $resource = MaterialRequisitionResource::class;
    
    public function getTitle(): string
    {
        return 'Finance Approval: ' . $this->record->document_number;
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (! in_array($this->record->status, ['Pending Finance'], true)) {
            Notification::make()
                ->title(__('This request is no longer at this stage.'))
                ->body(__('Current status') . ': ' . $this->record->status . '.')
                ->warning()
                ->send();

            $this->redirect(\App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]));
        }
    }

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
                'qty' => number_format((int) $item->qty, 0, ',', '.'),
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
            if (empty($item['material_id'])) {
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



    protected function afterSave(): void
    {
        $this->record->items()->delete();
        foreach ($this->itemsData as $item) {
            if (!empty($item['material_id'])) {
                // WAJIB di-parse: input qty dan price kini menampilkan pemisah
                // ribuan ("250.000"), dan bila disimpan mentah akan terbaca 250.
                $qty = MaterialRequisitionResource::parseNumber($item['qty'] ?? 0);
                $price = MaterialRequisitionResource::parseNumber($item['price'] ?? 0);

                $this->record->items()->create([
                    'material_id' => $item['material_id'],
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

    /** @return array<int, string> nama bahan yang harganya masih kosong */
    protected function itemsMissingPrice(): array
    {
        $kosong = [];

        foreach ($this->data['items'] ?? [] as $item) {
            if (empty($item['material_id'])) {
                continue;
            }

            if (MaterialRequisitionResource::parseNumber($item['price'] ?? 0) > 0) {
                continue;
            }

            $kosong[] = \App\Models\Material::find($item['material_id'])->name ?? ('#'.$item['material_id']);
        }

        return $kosong;
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
                if (! $this->stageStillOpen(['Pending Finance'], \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]))) {
                    return;
                }

                // Harga kosong = PO bernilai nol = hutang palsu; sama seperti kembarannya di Request Produk (audit pra-rilis, ED-02).
                $kosong = $this->itemsMissingPrice();

                if ($kosong !== []) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('Some prices are still empty, so the purchase order cannot be issued'))
                        ->body(__('A purchase order worth zero creates a fake payable and wrecks the payment-term calculation. These items still have no price').': '.implode(', ', $kosong).'.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                // Argumen KEDUA mematikan toast "Saved" bawaan Filament, supaya
                // pengguna tidak melihat dua toast sekaligus.
                $this->save(false, false);

                \Illuminate\Support\Facades\DB::transaction(function () {
                    $this->record->update([
                        'status' => 'PO Created',
                        'reject_note' => null,
                    ]);

                    $this->record->generatePurchaseOrder();
                });

                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'review_material_requisitions',
                    __('Material Request Approved'),
                    __('Approved by finance, the PO has been issued.'),
                    \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]),
                    'material-request-' . $this->record->id,
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
                if (! $this->stageStillOpen(['Pending Finance'], \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]))) {
                    return;
                }

                $this->record->update([
                    'status' => 'Returned to Purchasing',
                    'reject_note' => $data['reject_note'],
                ]);

                // Kembali ke PURCHASING, bukan ke pemohon: purchasing yang harus
                // memperbaiki harganya. Tombol ini memang bukan reject.
                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'review_material_requisitions',
                    __('Material Request Returned'),
                    __('Returned by finance, please review it again.'),
                    \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('review', ['record' => $this->record]),
                    'material-request-' . $this->record->id,
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
