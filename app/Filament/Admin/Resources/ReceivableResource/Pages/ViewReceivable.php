<?php

namespace App\Filament\Admin\Resources\ReceivableResource\Pages;

use App\Filament\Admin\Resources\ReceivableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReceivable extends ViewRecord
{
    protected static string $resource = ReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print Invoice'))
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('print.invoice', $this->record->invoice_id))
                ->openUrlInNewTab(),
        ];
    }
}
