<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use App\Models\CattleWeighing;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditCattleWeighing extends EditRecord
{
    protected static string $resource = CattleWeighingResource::class;

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
                ->url(fn ($record): string => route('cattle-weighing.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => \App\Models\Carcass::where('cattle_weighing_id', $record->id)->exists()),
            Actions\ForceDeleteAction::make()
                ->disabled(fn ($record) => \App\Models\Carcass::where('cattle_weighing_id', $record->id)->exists()),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if (\App\Models\Carcass::where('cattle_weighing_id', $this->getRecord()->id)->exists()) {
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

    /**
     * Halaman ini dulu sama sekali tidak punya ->disabled() -- satu-satunya
     * "penjagaan" adalah tombol Simpan yang disembunyikan begitu Carcass
     * sudah lahir, dan itu tidak menghalangi permintaan Livewire yang
     * dipaksa. Idiomnya sekarang sama dengan EditGoodsReceiptMaterial:
     * mount() mengalihkan navigasi baru, beforeSave() menolak tab yang
     * sudah terlanjur terbuka sebelum Carcass-nya lahir dari sesi lain.
     */
    public function mount($record): void
    {
        parent::mount($record);

        if (\App\Models\Carcass::where('cattle_weighing_id', $this->getRecord()->id)->exists()) {
            Notification::make()
                ->title(__('This Cattle Weighing has been processed into Carcass and is now read-only.'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function beforeSave(): void
    {
        $locked = CattleWeighing::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && \App\Models\Carcass::where('cattle_weighing_id', $locked->id)->exists()) {
            Notification::make()
                ->title(__('This Cattle Weighing has been processed into Carcass and is now read-only.'))
                ->warning()
                ->send();

            throw new Halt();
        }
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $record->calculateAndSaveFinancialLoss();
    }
}
