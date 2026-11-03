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
        // Dibuka dari halaman Belum Tukar Faktur (filter belum_tukar_faktur aktif): tombol lain tidak relevan, cukup Kembali.
        if (data_get($this->tableFilters, 'belum_tukar_faktur.isActive')) {
            return [
                Actions\Action::make('back_to_unexchanged')
                    ->label(__('Back'))
                    ->color('gray')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url(static::getResource()::getUrl('unexchanged')),
            ];
        }

        return [
            Actions\Action::make('detail_list')
                ->label(__('Detail'))
                ->color('info')
                ->icon('heroicon-o-list-bullet')
                ->outlined()
                ->extraAttributes(['class' => 'rounded-full', 'style' => 'width: 12rem; justify-content: center;'])
                ->url(static::getResource()::getUrl('detail_list')),

            Actions\Action::make('unexchanged_list')
                ->label(__('Not Yet Exchanged'))
                ->color('danger')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->outlined()
                ->extraAttributes(['class' => 'rounded-full', 'style' => 'width: 12rem; justify-content: center;'])
                ->url(static::getResource()::getUrl('unexchanged')),

            Actions\Action::make('draft_list')
                ->label(__('Draft Invoices'))
                ->color('warning')
                ->icon('heroicon-o-document-text')
                ->outlined()
                ->extraAttributes(['class' => 'rounded-full', 'style' => 'width: 12rem; justify-content: center;'])
                ->url(static::getResource()::getUrl('draft')),
        ];
    }
}
