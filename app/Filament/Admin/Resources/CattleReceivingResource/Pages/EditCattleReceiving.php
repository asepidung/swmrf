<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Models\CattleReceiving;
use Filament\Actions;
use App\Filament\Admin\Resources\CattleReceivingResource\Concerns\SavesUniqueEartags;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditCattleReceiving extends EditRecord
{
    use SavesUniqueEartags;

    protected static string $resource = CattleReceivingResource::class;

    /**
     * ->disabled() di form Resource cuma melindungi Repeater items yang
     * relationship-backed. Field polos langsung di model (doc_no, sv_ok,
     * skkh_ok, receive_date, note) TIDAK ikut terlindungi meski sama-sama
     * di bawah ->disabled() yang sama -- terbukti lewat set()+save() paksa
     * tetap tersimpan. Idiomnya sekarang sama dengan EditGoodsReceiptMaterial
     * dan EditCattleWeighing: mount() mengalihkan navigasi baru, beforeSave()
     * menolak tab yang sudah terlanjur terbuka sebelum ditimbang dari sesi
     * lain.
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->weighing()->exists()) {
            Notification::make()
                ->title(__('This receiving has already been weighed and cannot be edited.'))
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function beforeSave(): void
    {
        $locked = CattleReceiving::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && $locked->weighing()->exists()) {
            Notification::make()
                ->title(__('This receiving has already been weighed and cannot be edited.'))
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveGuardingEartags(fn (): Model => parent::handleRecordUpdate($record, $data));
    }



    protected function afterSave(): void
    {
        $this->issuePayable();
    }

    /**
     * Terbitkan utangnya, dan JANGAN diam bila tidak bisa.
     *
     * Utang yang gagal terbit tanpa pemberitahuan adalah kegagalan yang
     * paling mahal di modul ini: dokumennya tersimpan, layarnya terlihat
     * normal, dan tagihan supplier baru muncul berminggu-minggu kemudian
     * tanpa ada yang tahu asal selisihnya.
     */
    protected function issuePayable(): void
    {
        $receiving = $this->getRecord()->fresh(['items.cattleClass', 'purchaseCattle.items', 'supplier']);

        if ($receiving->syncPayable()) {
            return;
        }

        $unpriced = \App\Models\Payable::unpricedCattleClasses($receiving);

        Notification::make()
            ->warning()
            ->title(__('Payable not issued yet'))
            ->body($unpriced === []
                ? __('This receiving has no cattle recorded, so no payable was created.')
                : __('These cattle classes have no price on the PO, so the payable was not created: :classes. Add the price on the PO, then save this document again.', [
                    'classes' => implode(', ', $unpriced),
                ]))
            ->persistent()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('cattle-receiving.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => $record->weighing()->exists()),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->getRecord()->weighing()->exists()) {
            return [];
        }
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
