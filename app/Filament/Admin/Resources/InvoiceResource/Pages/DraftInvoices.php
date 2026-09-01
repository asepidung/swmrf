<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Models\DeliveryOrderReceipt;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class DraftInvoices extends Page implements HasTable
{
    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('Draft Invoices');
    }

    use InteractsWithTable;

    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'filament.admin.resources.invoice-resource.pages.draft-invoices';


    public function table(Table $table): Table
    {
        return $table
            ->query(
                DeliveryOrderReceipt::query()
                    ->whereDoesntHave('invoice')
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.delivery_order_number')
                    ->label(__('DO Number'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('create_invoice')
                    ->label(__('Proses Invoice'))
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->url(fn (DeliveryOrderReceipt $record) => InvoiceResource::getUrl('create', ['delivery_order_receipt_id' => $record->id]))
            ])
            ->defaultSort('id', 'desc');
    }
}
