<?php

namespace App\Filament\Admin\Resources\PurchaseMaterialResource\Pages;

use App\Filament\Admin\Resources\PurchaseMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseMaterial extends ViewRecord
{
    protected static string $resource = PurchaseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Print PO')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.po-material', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
                
            Actions\Action::make('back')
                ->label('Back to List')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
