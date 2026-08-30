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
            Actions\Action::make('payment')
                ->label(__('Receive Payment'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => auth()->user()?->hasPermission('receive_receivables') ?? false)
                ->url(fn () => ReceivableResource::getUrl('payment', ['record' => $this->record->id])),
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
