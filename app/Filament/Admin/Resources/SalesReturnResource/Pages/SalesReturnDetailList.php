<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use App\Models\SalesReturnItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = SalesReturnResource::class;

    protected static string $view = 'filament.admin.resources.sales-return-resource.pages.detail-list';

    public function getTitle(): string { return __('Sales Return Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(SalesReturnItem::query()->with(['salesReturn', 'product', 'warehouse', 'grade']))
            ->columns([
                Tables\Columns\TextColumn::make('salesReturn.return_number')
                    ->label(__('Return Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Return Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('salesReturn.customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Warehouse'))
                    ->sortable(),
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.sales-return-details-pdf', ['records' => $records])->setPaper('a4', 'landscape');
                            return response()->streamDownload(fn () => print($pdf->output()), 'Detail_Sales_Return_' . now()->format('Y-m-d') . '.pdf');
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
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Return Number', 'Return Date', 'Customer', 'Barcode', 'Product', 'Weight', 'Grade', 'Warehouse']));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->salesReturn->return_number ?? '',
                                        $record->created_at ? $record->created_at->format('Y-m-d') : '',
                                        $record->salesReturn->customer->name ?? '',
                                        $record->barcode ?? '',
                                        $record->product->name ?? '',
                                        (string) $record->weight,
                                        $record->grade->name ?? '',
                                        $record->warehouse->name ?? '',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Sales_Return_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('From Date')),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['until'] ?? now()->toDateString();

                        return $query->whereHas('salesReturn', function ($q) use ($from, $until) {
                            $q->whereDate('created_at', '>=', $from)
                              ->whereDate('created_at', '<=', $until);
                        });
                    }),
            ]);
    }
}
