<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Input Return Items')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Enter / scan goods'))
                ->icon('heroicon-o-bars-3-bottom-left')
                ->color('warning')
                ->url(fn () => SalesReturnResource::getUrl('input-items', ['record' => $this->record]))
                ->hidden(fn () => $this->record->status !== 'Draft'),

            Actions\Action::make('Print PDF')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Print Berita Acara'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('sales-return.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('Approve Return')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Approve Return'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Approve Sales Return'))
                ->modalDescription(__('Every item on this return will be put into stock.'))
                // Tombol ini MENAMBAH STOK, jadi izinnya sendiri -- bukan
                // menumpang izin menyunting, yang dipegang siapa pun yang
                // boleh membetulkan catatan.
                ->hidden(fn (): bool => $this->record->status !== 'Draft'
                    || $this->record->items->isEmpty()
                    || ! (auth()->user()?->hasPermission('approve_sales_returns') ?? false))
                ->action(function (): void {
                    try {
                        $this->record->approve();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Return Approved & Stock Updated'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('Unlock Return')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Unlock / Cancel Approval'))
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Unlock Sales Return'))
                ->modalDescription(__('Every item from this return that already went into stock will be pulled back out.'))
                // MENGHAPUS baris stok yang sudah ada -- lebih berbahaya
                // daripada menyetujui, jadi izinnya pun dipisah.
                ->hidden(fn (): bool => $this->record->status !== 'Approved'
                    || ! (auth()->user()?->hasPermission('unlock_sales_returns') ?? false))
                ->action(function (): void {
                    try {
                        $this->record->unlock();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Return Unlocked & Stock Reverted'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('Back')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => $this->getResource()::getUrl('index')),

            Actions\DeleteAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Delete'))
                ->icon('heroicon-o-trash')
                // Retur yang SUDAH DISETUJUI tidak boleh dihapus. Menghapusnya
                // tidak menarik stoknya kembali, jadi barangnya tetap di gudang
                // tanpa satu pun dokumen yang menjelaskan dari mana ia datang.
                // Yang benar: buka kuncinya dulu -- di situ stoknya ditarik dan
                // jejaknya tercatat -- baru returnya dihapus.
                ->hidden(fn (): bool => $this->record->status === 'Approved'),
            
            Actions\ForceDeleteAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Force Delete'))
                ->icon('heroicon-o-trash')
                // Retur yang SUDAH DISETUJUI tidak boleh dihapus. Menghapusnya
                // tidak menarik stoknya kembali, jadi barangnya tetap di gudang
                // tanpa satu pun dokumen yang menjelaskan dari mana ia datang.
                // Yang benar: buka kuncinya dulu -- di situ stoknya ditarik dan
                // jejaknya tercatat -- baru returnya dihapus.
                ->hidden(fn (): bool => $this->record->status === 'Approved'),
            
            Actions\RestoreAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Restore'))
                ->icon('heroicon-o-arrow-path'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }
}
