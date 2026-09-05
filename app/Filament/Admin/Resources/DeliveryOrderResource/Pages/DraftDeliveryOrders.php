<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Models\Tally;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DraftDeliveryOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DeliveryOrderResource::class;

    protected static string $view = 'filament.admin.resources.delivery-order-resource.pages.draft-delivery-orders';

    protected ?string $heading = 'Draft Delivery Orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tally::query()
                    ->where('status', 'locked')
                    ->doesntHave('deliveryOrder')
            )
            ->columns([
                Tables\Columns\TextColumn::make('tally_number')
                    ->label(__('Tally Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('salesOrder.customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesOrder.delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesOrder.po_number')
                    ->label(__('PO Number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('salesOrder.so_number')
                    ->label(__('SO Number'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('proses_do')
                    ->label(__('Process the delivery order'))
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->button()
                    ->url(fn (Tally $record): string => DeliveryOrderResource::getUrl('create', ['tally_id' => $record->id])),
            ]);
    }
}
