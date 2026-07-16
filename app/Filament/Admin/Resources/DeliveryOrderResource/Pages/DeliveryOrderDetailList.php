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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('pdf')
                        ->label(fn() => __('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.delivery-order-details-pdf', ['records' => $records])->setPaper('a4', 'landscape');
                            return response()->streamDownload(fn () => print($pdf->output()), 'Detail_Delivery_Order_' . now()->format('Y-m-d') . '.pdf');
                        }),
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(fn() => __('Excel'))
                        ->color('success')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['DO Number', 'Customer', 'Delivery Date', 'Product', 'Shipped Box', 'Shipped Weight', 'Received Box', 'Received Weight']));
                                foreach ($records as $record) {
                                    $receipt = \App\Models\DeliveryOrderReceipt::where('delivery_order_id', $record->delivery_order_id)->first();
                                    $receiptItem = null;
                                    if ($receipt) {
                                        $receiptItem = \App\Models\DeliveryOrderReceiptItem::where('delivery_order_receipt_id', $receipt->id)
                                            ->where('product_id', $record->product_id)
                                            ->first();
                                    }
                                    
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->deliveryOrder->delivery_order_number ?? '',
                                        $record->deliveryOrder->customer->name ?? '',
                                        $record->deliveryOrder->delivery_date ? \Carbon\Carbon::parse($record->deliveryOrder->delivery_date)->format('Y-m-d') : '',
                                        $record->product->name ?? '',
                                        (string) $record->box,
                                        (string) $record->weight,
                                        $receiptItem ? (string) $receiptItem->received_box : '-',
                                        $receiptItem ? (string) $receiptItem->received_weight : '-',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Delivery_Order_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->defaultSort('id', 'desc');
    }
}
