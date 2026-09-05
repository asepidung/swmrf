<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewSalesReturn extends ViewRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Print PDF')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Print Berita Acara'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('sales-return.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('Unlock Return')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Unlock / Cancel Approval'))
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Unlock Sales Return'))
                ->modalDescription(__('Every item from this return that already went into stock will be pulled back out.'))
                // Izinnya sendiri, sama seperti di halaman Edit. Tombol yang
                // sama pernah ada di DUA halaman dengan penjagaan berbeda --
                // menambal yang satu meninggalkan yang lain terbuka.
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

            Actions\EditAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip(__('Edit'))
                ->hidden(fn () => $this->record->status === 'Approved'),
        ];
    }
}
