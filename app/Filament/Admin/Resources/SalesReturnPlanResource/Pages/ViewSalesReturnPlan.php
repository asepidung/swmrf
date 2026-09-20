<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Models\SalesReturnPlan;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReturnPlan extends ViewRecord
{
    protected static string $resource = SalesReturnPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('sales-return-plan.print', $this->record))
                ->openUrlInNewTab(),

            // Issue #478: halaman item terpisah dihapus -- negosiasi
            // klaim (dan qty) selagi Submitted sekarang lewat halaman
            // Edit yang sama (header-nya otomatis terkunci di sana).
            // Received/Cancelled tidak punya tombol ini sama sekali.
            Actions\Action::make('edit_plan')
                ->label(__('Negotiate Claim'))
                ->icon('heroicon-o-list-bullet')
                ->visible(fn (): bool => $this->record->status === SalesReturnPlan::STATUS_SUBMITTED
                    && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_sales_return_plans') ?? false)))
                ->url(fn (): string => $this->getResource()::getUrl('edit', ['record' => $this->record])),

            // View hanya mensyaratkan `view_sales_return_plans` -- mengubah
            // status (batal) butuh `edit_sales_return_plans` sendiri,
            // dicek di visible() DAN diulang di dalam action() karena
            // visible() cuma menyembunyikan tombolnya, bukan menutup
            // metodenya dari panggilan Livewire langsung.
            Actions\Action::make('cancel_plan')
                ->label(__('Cancel Plan'))
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === SalesReturnPlan::STATUS_SUBMITTED
                    && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_sales_return_plans') ?? false)))
                ->action(function (): void {
                    if (! (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_sales_return_plans') ?? false))) {
                        Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                        return;
                    }

                    try {
                        $this->record->cancel();
                    } catch (\RuntimeException $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Plan cancelled.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url(fn () => $this->getResource()::getUrl('index')),
        ];
    }
}
