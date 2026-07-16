<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use App\Models\PurchaseCattleItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PurchaseCattleDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PurchaseCattleResource::class;

    protected static string $view = 'filament.admin.resources.purchase-cattle-resource.pages.detail-list';

    public function getTitle(): string { return __('PO Cattle Items Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(PurchaseCattleItem::query()->with(['purchaseCattle.supplier', 'cattleClass']))
            ->columns([
                Tables\Columns\TextColumn::make('purchaseCattle.document_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('purchaseCattle.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseCattle.created_at')
                    ->label(__('PO Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cattleClass.name')
                    ->label(__('Cattle Class'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Qty (Head)'))
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
                Tables\Columns\TextColumn::make('item_notes')
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
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['po_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['po_until'] ?? now()->toDateString();

                        return $query->whereHas('purchaseCattle', function ($q) use ($from, $until) {
                            $q->when(
                                $from,
                                fn ($q, $date) => $q->whereDate('created_at', '>=', $date)
                            )->when(
                                $until,
                                fn ($q, $date) => $q->whereDate('created_at', '<=', $date)
                            );
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['po_from'] ?? null) {
                            $indicators[] = 'From: ' . Carbon::parse($data['po_from'])->format('d M Y');
                        }
                        if ($data['po_until'] ?? null) {
                            $indicators[] = 'Until: ' . Carbon::parse($data['po_until'])->format('d M Y');
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
                                    'PO Number', 'PO Date', 'Supplier', 'Cattle Class', 'Qty (Head)', 'Price', 'Subtotal', 'Item Note'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->purchaseCattle?->document_number ?? '',
                                        $record->purchaseCattle?->created_at ?? '',
                                        $record->purchaseCattle?->supplier?->name ?? '',
                                        $record->cattleClass?->name ?? '',
                                        $record->qty ?? '',
                                        $record->price ?? '',
                                        $record->subtotal ?? '',
                                        $record->item_notes ?? ''
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.purchase-cattle-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'purchase-cattle-details.pdf');
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