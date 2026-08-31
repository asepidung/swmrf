<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use Filament\Actions;
use App\Filament\Admin\Resources\CattleReceivingResource\Concerns\SavesUniqueEartags;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCattleReceiving extends EditRecord
{
    use SavesUniqueEartags;

    protected static string $resource = CattleReceivingResource::class;

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
