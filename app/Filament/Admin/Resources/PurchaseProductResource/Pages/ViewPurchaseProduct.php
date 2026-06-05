<?php

namespace App\Filament\Admin\Resources\PurchaseProductResource\Pages;

use App\Filament\Admin\Resources\PurchaseProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseProduct extends ViewRecord
{
    protected static string $resource = PurchaseProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Print PO')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.po-product', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
                
            Actions\Action::make('back')
                ->label('Back to List')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
