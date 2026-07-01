<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReturn extends ViewRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Print PDF')
                ->tooltip('Print Berita Acara')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->hiddenLabel()
                ->url(fn () => route('sales-return.pdf', $this->record))
                ->openUrlInNewTab(),
                
            Actions\EditAction::make()
                ->hidden(fn () => $this->record->status === 'Approved'),
        ];
    }
}
