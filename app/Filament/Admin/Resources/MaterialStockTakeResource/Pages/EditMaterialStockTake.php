<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use App\Models\MaterialStockTake;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use Illuminate\Support\Facades\DB;

class EditMaterialStockTake extends EditRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    public function getTitle(): string
    {
        return __('Input / Review Stock Opname');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        // Hide save button if not in progress/draft
        if (!in_array($this->record->status, MaterialStockTake::STATUS_SEDANG_MENGHITUNG, true)) {
            return [];
        }

        $actions = parent::getFormActions();
        
        // Hide default cancel button at the bottom
        $actions = array_filter($actions, fn ($action) => $action->getName() !== 'cancel');
        
        return $actions;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Actions\Action::make('cancel_button')
            ->label(__('Cancel'))
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        if ($this->record->isCountable()) {
            $actions[] = Actions\Action::make('submit_for_review')
                ->label(__('Submit for Review'))
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading(__('Submit this stock count for review?'))
                ->modalDescription(__('Once submitted, the counts can no longer be edited and the variance is shown for review.'))
                ->action(function () {
                    $this->record->update(['status' => MaterialStockTake::STATUS_REVIEW]);
                    Notification::make()->title(__('Sent for review.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                });
        }

        if ($this->record->status === MaterialStockTake::STATUS_REVIEW) {
            $actions[] = Actions\Action::make('complete_opname')
                ->label(__('Complete Opname'))
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading(__('Finish this stock count?'))
                ->modalDescription(__('Is everything counted carefully? Once you press this, nothing can be changed. Every difference cuts or adds stock permanently, and anything left uncounted is treated as missing.'))
                // Tombol ini MENGUBAH STOK secara permanen, jadi izinnya
                // sendiri -- sama seperti padanannya di opname daging.
                ->visible(fn (): bool => auth()->user()?->isProgrammer()
                    || (auth()->user()?->hasPermission('finish_material_stock_takes') ?? false))
                ->action(function () {
                    // Satu jalur, di modelnya.
                    //
                    // Yang ada di sini sebelumnya MENIMPA stok dengan angka
                    // hitungan -- menimpa qty dengan physical_qty -- lalu menulis
                    // stok dan buku besar dengan tangan, tanpa penguncian, dan
                    // melewati `StockService` sepenuhnya -- sementara tombol
                    // yang satu lagi menambahkan SELISIH lewat service itu.
                    // Dua arti untuk satu tindakan yang sama.
                    $this->record->applyToStock();

                    Notification::make()->title(__('The stock count is finished and the stock has been updated.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // Penjaga hapus yang sama dengan halaman lain. Di sini dulu ketiganya
        // tanpa penjagaan apa pun, jadi opname yang sudah dihitung -- bahkan
        // yang sudah selesai -- bisa dibuang lewat pintu ini.
        $actions[] = Actions\DeleteAction::make()
            ->visible(fn (): bool => $this->record->isDeletable());
        $actions[] = Actions\ForceDeleteAction::make()
            ->visible(fn (): bool => auth()->user()?->isProgrammer() ?? false);
        $actions[] = Actions\RestoreAction::make();

        return $actions;
    }
}
