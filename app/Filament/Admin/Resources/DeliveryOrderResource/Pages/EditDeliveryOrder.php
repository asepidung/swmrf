<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
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

        if ($this->record->status === 'Approved') {
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


            Actions\Action::make('approve')
                ->label('')
                ->tooltip(__('Approve'))
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->iconButton()
                ->visible(fn () => $this->record->status === 'Ready')
                ->url(fn () => DeliveryOrderResource::getUrl('approve', ['record' => $this->record->id])),



            Actions\DeleteAction::make()
                ->label('')
                ->tooltip(__('Delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->iconButton(),
        ];
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
