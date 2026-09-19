<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('expense.print', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->label(__('Back'))
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    /**
     * Pola sama dengan `EditGoodsReceiptMaterial`: `mount()` menolak
     * MEMBUKA halaman untuk expense yang sudah terkunci (Settled/Cancelled).
     */
    public function mount($record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status !== Expense::STATUS_OPEN) {
            Notification::make()
                ->title(__('This expense is locked and can no longer be edited.'))
                ->danger()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    /**
     * Lapis kedua: tab yang sudah terbuka sebelum expense-nya di-settle/
     * dibatalkan dari sesi lain masih bisa menyimpan tanpa ini. Baris
     * dikunci dan dibaca ULANG di sini sebelum penyimpanan benar-benar
     * terjadi -- sama seperti `EditGoodsReceiptMaterial::beforeSave()`.
     */
    protected function beforeSave(): void
    {
        $locked = Expense::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && $locked->status !== Expense::STATUS_OPEN) {
            Notification::make()
                ->title(__('This expense is locked and can no longer be edited.'))
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
