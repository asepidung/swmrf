<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Models\SalesReturnPlan;
use App\Models\SalesReturnPlanItem;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Draft DAN Submitted (issue #478 -- sebelumnya cuma Draft, negosiasi
 * klaim saat Submitted lewat halaman item terpisah yang sekarang
 * dihapus). Idiom mount()+beforeSave() sama dengan
 * EditCattleWeighing/EditGoodsReceiptMaterial: mount() mengalihkan
 * navigasi baru, beforeSave() menolak tab yang sudah terlanjur terbuka
 * sebelum plan-nya berubah status dari sesi lain.
 *
 * Header terkunci untuk Submitted lewat `->disabled()` di
 * `SalesReturnPlanResource::form()` (ditegakkan lagi di
 * `SalesReturnPlan::booted()` `updating()`); item hanya boleh dinego
 * qty-nya (tidak bisa tambah/hapus produk, `SalesReturnPlanItem::booted()`).
 */
class EditSalesReturnPlan extends EditRecord
{
    protected static string $resource = SalesReturnPlanResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        if (! in_array($this->getRecord()->status, [SalesReturnPlan::STATUS_DRAFT, SalesReturnPlan::STATUS_SUBMITTED], true)) {
            Notification::make()
                ->title(__('This sales return plan can no longer be edited because it is no longer a draft.'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function beforeSave(): void
    {
        $locked = SalesReturnPlan::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && ! in_array($locked->status, [SalesReturnPlan::STATUS_DRAFT, SalesReturnPlan::STATUS_SUBMITTED], true)) {
            Notification::make()
                ->title(__('This sales return plan can no longer be edited because it is no longer a draft.'))
                ->warning()
                ->send();

            throw new Halt();
        }
    }

    /** Isi Repeater dari relasi yang sudah ada -- `id` dipakai afterSave() untuk membedakan baris lama dari baru. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $items = [];

        foreach ($this->record->items as $item) {
            $items['item_'.$item->id] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'claimed_weight' => $item->claimed_weight,
                'claimed_qty_pcs' => $item->claimed_qty_pcs,
                'note' => $item->note,
            ];
        }

        $data['items'] = $items;

        return $data;
    }

    /**
     * Header dan item disimpan dalam SATU transaksi (issue #478) --
     * kalau ada baris yang ditolak validasi server (klaim > terkirim di
     * DO, produk dihapus padahal sudah Submitted, dst), SELURUH
     * penyimpanan batal, bukan header saja yang berhasil sementara
     * itemnya gagal separuh jalan.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $itemsData = $data['items'] ?? [];
        unset($data['items']);

        try {
            return DB::transaction(function () use ($record, $data, $itemsData): Model {
                $record->update($data);

                $submittedIds = collect($itemsData)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

                // Baris yang hilang dari Repeater dibandingkan basis data
                // berarti dihapus orangnya -- hanya sungguhan berlaku
                // selagi Draft, `SalesReturnPlanItem::booted()` menolak
                // kalau ini terjadi di luar itu.
                $record->items()->whereNotIn('id', $submittedIds)->get()->each(fn (SalesReturnPlanItem $item) => $item->delete());

                foreach ($itemsData as $item) {
                    $payload = [
                        'product_id' => $item['product_id'],
                        'claimed_weight' => $item['claimed_weight'],
                        'claimed_qty_pcs' => $item['claimed_qty_pcs'] ?? null,
                        'note' => $item['note'] ?? null,
                    ];

                    if (! empty($item['id'])) {
                        SalesReturnPlanItem::find($item['id'])?->update($payload);
                    } else {
                        $record->items()->create($payload);
                    }
                }

                return $record;
            });
        } catch (\Exception $e) {
            report($e);
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),

            Actions\Action::make('submit_plan')
                ->label(__('Submit Plan'))
                ->color('success')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === SalesReturnPlan::STATUS_DRAFT)
                ->action(function () {
                    try {
                        $this->getRecord()->submit();
                    } catch (\RuntimeException $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Plan submitted.'))->success()->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                }),

            // Submitted tidak lagi punya tombol Submit (sudah terkirim),
            // tapi tetap bisa negosiasi qty klaim di sini -- redirect
            // balik ke View sesudah simpan, konsisten dengan alur
            // "buka dari View, kembali ke View" untuk plan yang sudah
            // berjalan.
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->status === SalesReturnPlan::STATUS_DRAFT),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getRecord()->status === SalesReturnPlan::STATUS_DRAFT
            ? $this->getResource()::getUrl('index')
            : $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
