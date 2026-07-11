<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Models\GoodsReceiptProductItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class GoodsReceiptProductDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GoodsReceiptProductResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.detail-list';

    public function getTitle(): string { return __('Beef Receipt Items Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoodsReceiptProductItem::query()->with(['goodsReceiptProduct.supplier', 'goodsReceiptProduct.purchaseProduct', 'goodsReceiptProduct.createdBy', 'product', 'grade']))
            ->columns([
                Tables\Columns\TextColumn::make('goodsReceiptProduct.gr_number')
                    ->label(__('GR Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('goodsReceiptProduct.receive_date')
                    ->label(__('Receive Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsReceiptProduct.sj_number')
                    ->label(__('Surat Jalan'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsReceiptProduct.purchaseProduct.po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsReceiptProduct.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->badge()
                    ->color(fn ($state) => str_contains(strtolower($state), 'chill') ? 'info' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->numeric(2, ',', '.')
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->numeric()
                    ->alignCenter()
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
                Tables\Columns\TextColumn::make('goodsReceiptProduct.createdBy.name')
                    ->label(__('Created By'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('gr_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('gr_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['gr_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['gr_until'] ?? now()->toDateString();

                        return $query->whereHas('goodsReceiptProduct', function ($q) use ($from, $until) {
                            $q->when(
                                $from,
                                fn ($q, $date) => $q->whereDate('receive_date', '>=', $date)
                            )->when(
                                $until,
                                fn ($q, $date) => $q->whereDate('receive_date', '<=', $date)
                            );
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['gr_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['gr_from'])->format('d M Y');
                        }
                        if ($data['gr_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['gr_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ExportAction::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->exporter(\App\Filament\Exports\GoodsReceiptProductItemExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.goods-receipt-product-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'goods-receipt-product-details.pdf');
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
