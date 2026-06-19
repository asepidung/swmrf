<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('print.invoice', $this->record->id))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
