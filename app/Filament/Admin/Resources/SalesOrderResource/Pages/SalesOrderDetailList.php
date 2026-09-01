<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use App\Models\SalesOrderItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;

class SalesOrderDetailList extends Page implements HasTable
{
    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('Sales Order Items Detail');
    }

    use InteractsWithTable;

    protected static string $resource = SalesOrderResource::class;

    protected static string $view = 'filament.admin.resources.sales-order.pages.detail-list';


    public function table(Table $table): Table
    {
        return $table
            ->query(SalesOrderItem::query()->with(['salesOrder.customer', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('salesOrder.so_number')
                    ->label(__('SO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('salesOrder.customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salesOrder.delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->numeric()
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('do_sent')
                    ->label(__('DO Sent'))
                    ->state(function (SalesOrderItem $record) {
                        $sentWeight = \App\Models\DeliveryOrderItem::whereHas('deliveryOrder', function ($q) use ($record) {
                            $q->where('sales_order_id', $record->sales_order_id)
                              ->whereNotIn('status', ['cancelled', 'canceled']);
                        })
                        ->where('product_id', $record->product_id)
                        ->sum('weight');

                        return $sentWeight > 0 ? number_format($sentWeight, 2, ',', '.') : '-';
                    })
                    ->alignRight()
                    ->sortable(false),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->numeric()
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->label(__('Discount (%)'))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
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
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['delivery_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['delivery_until'] ?? now()->toDateString();

                        return $query->whereHas('salesOrder', function ($q) use ($from, $until) {
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
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'SO Number', 'Customer', 'Delivery Date', 'Product', 'Weight', 'Price', 'Discount (%)', 'Note'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->salesOrder?->so_number ?? '',
                                        $record->salesOrder?->customer?->name ?? '',
                                        $record->salesOrder?->delivery_date ?? '',
                                        $record->product?->name ?? '',
                                        $record->weight ?? '',
                                        $record->price ?? '',
                                        $record->discount ?? '',
                                        $record->note ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                    Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.sales-order-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'sales-order-details.pdf');
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
