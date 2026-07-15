<?php

namespace App\Filament\Admin\Resources\PurchaseMaterialResource\Pages;

use App\Filament\Admin\Resources\PurchaseMaterialResource;
use App\Models\PurchaseMaterialItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class PurchaseMaterialDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PurchaseMaterialResource::class;

    protected static string $view = 'filament.admin.resources.purchase-material-resource.pages.detail-list';

    protected static ?string $title = 'PO Material Items Detail';

    public function table(Table $table): Table
    {
        return $table
            ->query(PurchaseMaterialItem::query()->with(['purchaseMaterial.supplier', 'material']))
            ->columns([
                Tables\Columns\TextColumn::make('purchaseMaterial.po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('purchaseMaterial.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseMaterial.po_date')
                    ->label(__('PO Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Material'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Qty'))
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

                        return $query->whereHas('purchaseMaterial', function ($q) use ($from, $until) {
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
                                    'PO Number', 'PO Date', 'Supplier', 'Material', 'Qty', 'Price', 'Subtotal'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->purchaseMaterial?->po_number ?? '',
                                        $record->purchaseMaterial?->po_date ?? '',
                                        $record->purchaseMaterial?->supplier?->name ?? '',
                                        $record->material?->name ?? '',
                                        $record->qty ?? '',
                                        $record->price ?? '',
                                        $record->subtotal ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.purchase-material-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'purchase-material-details.pdf');
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
