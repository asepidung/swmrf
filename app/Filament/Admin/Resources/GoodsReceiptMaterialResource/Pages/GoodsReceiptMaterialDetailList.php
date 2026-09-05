<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use App\Models\GoodsReceiptMaterialItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class GoodsReceiptMaterialDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-material-resource.pages.detail-list';

    public function getTitle(): string { return __('Material Receipt Items Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoodsReceiptMaterialItem::query()->with(['goodsReceiptMaterial.supplier', 'goodsReceiptMaterial.purchaseMaterial', 'goodsReceiptMaterial.createdBy', 'material']))
            ->columns([
                Tables\Columns\TextColumn::make('goodsReceiptMaterial.gr_number')
                    ->label(__('GR Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('goodsReceiptMaterial.receive_date')
                    ->label(__('Receive Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsReceiptMaterial.sj_number')
                    ->label(__('Delivery Note'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsReceiptMaterial.purchaseMaterial.po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsReceiptMaterial.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Material'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty_received')
                    ->label(__('Qty Received'))
                    ->numeric(2, ',', '.')
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
                Tables\Columns\TextColumn::make('goodsReceiptMaterial.createdBy.name')
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

                        return $query->whereHas('goodsReceiptMaterial', function ($q) use ($from, $until) {
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
                                    __('GR Number'), __('Receive Date'), __('Delivery Note'), __('PO Number'), __('Supplier'), __('Material'), __('Qty Received'), __('Price'), __('Subtotal'), __('Created By')
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->goodsReceiptMaterial?->gr_number ?? '',
                                        $record->goodsReceiptMaterial?->receive_date ?? '',
                                        $record->goodsReceiptMaterial?->sj_number ?? '',
                                        $record->goodsReceiptMaterial?->purchaseMaterial?->po_number ?? '',
                                        $record->goodsReceiptMaterial?->supplier?->name ?? '',
                                        $record->material?->name ?? '',
                                        $record->qty_received ?? '',
                                        $record->price ?? '',
                                        $record->subtotal ?? '',
                                        $record->goodsReceiptMaterial?->createdBy?->name ?? ''
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.goods-receipt-material-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'goods-receipt-material-details.pdf');
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
