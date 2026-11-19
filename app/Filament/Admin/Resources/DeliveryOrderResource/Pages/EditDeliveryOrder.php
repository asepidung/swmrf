<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\TallyItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditDeliveryOrder extends EditRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        // Hanya `Ready` yang boleh disunting; Approved/Invoiced sudah punya bukti terima atau tagihan (DO-03).
        if ($this->record->status !== 'Ready') {
            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record->id]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('')
                ->tooltip(__('Back'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->iconButton()
                ->url($this->getResource()::getUrl('index')),


            Actions\Action::make('print')
                ->label('')
                ->tooltip(__('Print'))
                ->color('info')
                ->icon('heroicon-o-printer')
                ->iconButton()
                ->url(fn () => route('print.delivery-order', ['record' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('approve')
                ->label('')
                ->tooltip(__('Approve'))
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->iconButton()
                ->visible(fn (): bool => $this->record->status === 'Ready'
                    && (auth()->user()?->isProgrammer()
                        || (auth()->user()?->hasPermission('approve_delivery_orders') ?? false)))
                ->url(fn () => DeliveryOrderResource::getUrl('approve', ['record' => $this->record->id])),



            Actions\Action::make('cancel_delivery')
                ->label('')
                ->tooltip(__('Cancel Delivery Order'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->iconButton()
                ->visible(fn (): bool => $this->record->status === 'Ready'
                    && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('delete_delivery_orders') ?? false)))
                ->authorize(fn (): bool => auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('delete_delivery_orders') ?? false))
                ->requiresConfirmation()
                ->modalHeading(__('Cancel Delivery Order'))
                ->modalDescription(__('The delivery order, its tally, and the sales order are cancelled, and the tallied goods go back to stock. This cannot be undone.'))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label(__('Reason'))->required()->maxLength(300)->rows(2),
                ])
                ->action(function (array $data) {
                    if ($galat = $this->record->cancelDelivery($data['reason'] ?? null)) {
                        Notification::make()->title($galat)->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Delivery Order cancelled'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->label('')
                ->tooltip(__('Delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->iconButton()
                ->visible(fn (): bool => $this->record->status === 'Ready')
                ->before(function (Actions\DeleteAction $action) {
                    if (\App\Models\DeliveryOrder::whereKey($this->record->getKey())->value('status') !== 'Ready') {
                        Notification::make()->title(__('This delivery order has already moved on, so it cannot be deleted.'))->danger()->send();
                        $action->cancel();
                    }
                }),
        ];
    }

    /** Tab lama tidak boleh menyimpan sesudah surat jalan berpindah dari Ready (ED-01). */
    protected function beforeSave(): void
    {
        if (DeliveryOrder::query()->whereKey($this->record->getKey())->value('status') !== 'Ready') {
            Notification::make()->title(__('This delivery order has already moved on, so it cannot be saved.'))->danger()->send();
            $this->halt();
        }

        // RPT-01: baris surat jalan mengikuti tally-nya; jumlahnya tidak boleh diubah dari browser.
        if (! \App\Support\RepeaterRows::unchanged($this->record->items()->pluck('id'), $this->data['items'] ?? null)) {
            Notification::make()->title(__('The item rows do not match this document, so it was not saved. Reload the page.'))->danger()->send();
            $this->halt();
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
