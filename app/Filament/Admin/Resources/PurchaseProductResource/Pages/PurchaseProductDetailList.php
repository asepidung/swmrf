<?php

namespace App\Filament\Admin\Resources\PurchaseProductResource\Pages;

use App\Filament\Admin\Resources\PurchaseProductResource;
use App\Models\PurchaseProductItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class PurchaseProductDetailList extends Page implements HasTable
{
    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('PO Product Items Detail');
    }

    use InteractsWithTable;

    protected static string $resource = PurchaseProductResource::class;

    protected static string $view = 'filament.admin.resources.purchase-product-resource.pages.detail-list';


    public function table(Table $table): Table
    {
        return $table
            ->query(PurchaseProductItem::query()->with(['purchaseProduct.supplier', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('purchaseProduct.po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('purchaseProduct.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseProduct.po_date')
                    ->label(__('PO Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Qty'))
                    ->numeric(0, ',', '.')
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subtotal')
                    ->label(__('Subtotal'))
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\Filter::make('po_date')
                    ->form([
                        Forms\Components\DatePicker::make('po_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('po_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['po_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['po_until'] ?? now()->toDateString();

                        return $query->whereHas('purchaseProduct', function ($q) use ($from, $until) {
                            $q->when(
                                $from,
                                fn ($q, $date) => $q->whereDate('po_date', '>=', $date)
                            )->when(
                                $until,
                                fn ($q, $date) => $q->whereDate('po_date', '<=', $date)
                            );
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['po_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['po_from'])->format('d M Y');
                        }
                        if ($data['po_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['po_until'])->format('d M Y');
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
                                    'PO Number', 'PO Date', 'Supplier', 'Product', 'Qty', 'Price', 'Subtotal', 'Note'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->purchaseProduct?->po_number ?? '',
                                        $record->purchaseProduct?->po_date ?? '',
                                        $record->purchaseProduct?->supplier?->name ?? '',
                                        $record->product?->name ?? '',
                                        $record->qty ?? '',
                                        $record->price ?? '',
                                        $record->subtotal ?? '',
                                        $record->note ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.purchase-product-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'purchase-product-details.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->defaultSort('id', 'desc');
    }
}
