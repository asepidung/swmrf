<?php

namespace App\Filament\Admin\Resources\CostingResource\Pages;

use App\Filament\Admin\Resources\CostingResource;
use App\Models\Costing;
use App\Services\CostingCalculator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

/**
 * Hanya untuk costing `Draft` -- pola mount()+beforeSave() sama dengan
 * EditGoodsReceiptMaterial/EditSalesReturnPlan: mount() mengalihkan
 * navigasi baru begitu sudah Locked, beforeSave() menolak tab yang
 * sudah terlanjur terbuka sebelum status berubah dari sesi lain.
 */
class EditCosting extends EditRecord
{
    protected static string $resource = CostingResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status !== Costing::STATUS_DRAFT) {
            Notification::make()
                ->title(__('This costing is locked and can no longer be changed.'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    /** Menyimpan = costing mulai tampil di daftar Costings. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['saved_at'] = $this->getRecord()->saved_at ?? now();

        return $data;
    }

    protected function beforeSave(): void
    {
        $locked = Costing::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && $locked->status !== Costing::STATUS_DRAFT) {
            Notification::make()
                ->title(__('This costing is locked and can no longer be changed.'))
                ->warning()
                ->send();

            throw new Halt();
        }
    }

    /**
     * Overhead per kg diubah di form: hitung ulang costing dengan harga yang sedang tertera, simpan,
     * lalu muat ulang halaman (supaya HPP tiap baris ikut berubah).
     */
    public function applyOverhead(float $overheadPerKg): void
    {
        $record = $this->getRecord();

        if ($record->status !== Costing::STATUS_DRAFT) {
            return;
        }

        try {
            $result = CostingCalculator::forBoning($record->boning, $overheadPerKg, $record->currentPrices())->calculate();
            $record->recalculate($result);
        } catch (\Exception $e) {
            report($e);
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

            return;
        }

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
    }

    /** Tarik ulang hasil boning (produk dan berat) selagi costing masih Draft; harga yang sudah diisi dipertahankan. */
    public function refreshFromBoning(): void
    {
        $this->applyOverhead((float) $this->getRecord()->overhead_per_kg);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),

            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn (): string => route('print.costing', $this->getRecord()))
                ->openUrlInNewTab(),

            Actions\Action::make('refresh_from_boning')
                ->label(__('Refresh from boning'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('Re-read the products and weights from the boning. Prices already filled in are kept.'))
                ->visible(fn (): bool => ! $this->getRecord()->boning?->kunci)
                ->action(fn () => $this->refreshFromBoning()),

            Actions\DeleteAction::make(),

            Actions\Action::make('lock')
                ->label(__('Lock'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->disabled(fn (): bool => ! $this->getRecord()->boning?->kunci)
                ->tooltip(fn (): ?string => $this->getRecord()->boning?->kunci ? null : __('Lock the boning first: the costing can only be locked after the boning is locked.'))
                ->visible(fn (): bool => auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('lock_costings') ?? false))
                ->action(function () {
                    if (! (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('lock_costings') ?? false))) {
                        Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                        return;
                    }

                    try {
                        $this->getRecord()->lock();
                    } catch (\RuntimeException $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Costing locked.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                }),

        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
