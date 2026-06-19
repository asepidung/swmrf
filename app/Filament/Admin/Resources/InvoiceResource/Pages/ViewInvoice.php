<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Back'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('print.invoice', $this->record->id))
                ->openUrlInNewTab(),
        ];
    }
}
