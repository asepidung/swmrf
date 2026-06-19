<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail'))
                ->color('info')
                ->icon('heroicon-o-list-bullet')
                ->url(static::getResource()::getUrl('detail_list')),

            Actions\Action::make('draft_list')
                ->label(__('Draft Invoices'))
                ->color('warning')
                ->icon('heroicon-o-document-text')
                ->url(static::getResource()::getUrl('draft')),
        ];
    }
}
