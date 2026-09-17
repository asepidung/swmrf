<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Models\MaterialRequisition;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Edit Request: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    /**
     * Halaman ini adalah satu-satunya jalur ubah item/harga/qty request yang
     * TIDAK punya penjagaan status sama sekali -- beda dari Review dan
     * Finance Approval yang memang sengaja tetap bisa diedit di tahapnya
     * masing-masing. Begitu status lewat "Requested" (sudah direview,
     * disetujui finance, bahkan PO-nya sudah terbit), dokumen ini seharusnya
     * jadi riwayat, sama seperti due_date yang sudah lebih dulu dikunci lewat
     * `->disabled()` di form -- hanya saja penguncian itu tidak pernah
     * menjalar ke Repeater item maupun ke halamannya sendiri.
     *
     * Idiomnya sama dengan EditGoodsReceiptMaterial: `mount()` mengalihkan
     * navigasi baru, `beforeSave()` menolak tab yang sudah terlanjur terbuka
     * sebelum statusnya berubah dari sesi lain.
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status !== 'Requested') {
            Notification::make()
                ->title(__('This request can no longer be edited because it has moved past the Requested stage.'))
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function beforeSave(): void
    {
        $locked = MaterialRequisition::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && $locked->status !== 'Requested') {
            Notification::make()
                ->title(__('This request can no longer be edited because it has moved past the Requested stage.'))
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
                'qty' => (float) $item->qty,
                'price' => (float) $item->price,
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            // Tombolnya disembunyikan begitu PO sudah terbit -- penjagaan
            // sungguhan ada di MaterialRequisition::deleting(), ini cuma
            // mencegah orang menabraknya lewat jalur normal.
            Actions\DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->purchaseMaterial()->exists()),
            Actions\RestoreAction::make(),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
