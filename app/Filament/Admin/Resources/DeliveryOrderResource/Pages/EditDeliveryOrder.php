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
