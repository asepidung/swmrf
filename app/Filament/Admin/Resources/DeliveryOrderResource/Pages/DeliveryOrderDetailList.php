<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrderItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class DeliveryOrderDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DeliveryOrderResource::class;

    protected static string $view = 'filament.admin.resources.delivery-order-resource.pages.detail-list';

    protected static ?string $title = 'Delivery Order Items Detail';

    public function table(Table $table): Table
    {
        return $table
            ->query(DeliveryOrderItem::query()->with(['deliveryOrder.customer', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('deliveryOrder.delivery_order_number')
                    ->label(__('DO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('deliveryOrder.customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('box')
                    ->label(__('Shipped Box'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Shipped Weight'))
                    ->numeric(2)
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_box')
                    ->label(__('Received Box'))
                    ->alignCenter()
                    ->getStateUsing(function (DeliveryOrderItem $record) {
                        $receipt = \App\Models\DeliveryOrderReceipt::where('delivery_order_id', $record->delivery_order_id)->first();
                        if (!$receipt) return '-';
                        $receiptItem = $receipt->items()->where('product_id', $record->product_id)->first();
                        return $receiptItem?->box ?? 0;
                    }),
                Tables\Columns\TextColumn::make('received_weight')
                    ->label(__('Received Weight'))
                    ->numeric(2)
                    ->alignRight()
                    ->getStateUsing(function (DeliveryOrderItem $record) {
                        $receipt = \App\Models\DeliveryOrderReceipt::where('delivery_order_id', $record->delivery_order_id)->first();
                        if (!$receipt) return '-';
                        $receiptItem = $receipt->items()->where('product_id', $record->product_id)->first();
                        return $receiptItem?->weight ?? 0.00;
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('delivery_from')
                            ->label(__('From Date')),
                        \Filament\Forms\Components\DatePicker::make('delivery_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['delivery_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['delivery_until'] ?? now()->toDateString();

                        return $query->whereHas('deliveryOrder', function ($q) use ($from, $until) {
                            $q->when(
                                $from,
                                fn ($q, $date) => $q->whereDate('delivery_date', '>=', $date)
                            )->when(
                                $until,
                                fn ($q, $date) => $q->whereDate('delivery_date', '<=', $date)
                            );
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['delivery_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['delivery_from'])->format('d M Y');
                        }
                        if ($data['delivery_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['delivery_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('back')
                    ->label(__('Back'))
                    ->icon('heroicon-o-arrow-left')
                    ->color('secondary')
                    ->url(static::getResource()::getUrl('index')),
            ])
            ->defaultSort('id', 'desc');
    }
}
