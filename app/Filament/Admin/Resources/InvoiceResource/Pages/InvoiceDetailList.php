<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Models\InvoiceReconciliation;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;

class InvoiceDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'filament.admin.resources.invoice-resource.pages.detail-list';

    protected static ?string $title = 'Invoice Items Detail';

    public function table(Table $table): Table
    {
        return $table
            ->query(InvoiceReconciliation::query()->with(['invoice.customer', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('invoice.customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.invoice_date')
                    ->label(__('Invoice Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item_name')
                    ->label(__('Product / Charge'))
                    ->state(fn ($record) => $record->row_type === 'product' ? ($record->product?->name ?? '-') : $record->charge_name)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                              ->orWhere('charge_name', 'like', "%{$search}%");
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->orderBy('row_type', $direction)->orderBy('charge_name', $direction);
                    }),


                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight / Qty'))
                    ->numeric(2)
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->numeric(0, ',', '.')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_percent')
                    ->label(__('Disc %'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_rp')
                    ->label(__('Disc Rp'))
                    ->numeric(0, ',', '.')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->numeric(0, ',', '.')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('invoice_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('date_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['date_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['date_until'] ?? now()->toDateString();

                        return $query->whereHas('invoice', function ($q) use ($from, $until) {
                            $q->when($from, fn ($q, $date) => $q->whereDate('invoice_date', '>=', $date))
                              ->when($until, fn ($q, $date) => $q->whereDate('invoice_date', '<=', $date));
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['date_from'])->format('d M Y');
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['date_until'])->format('d M Y');
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
                                    'Invoice Number', 'Customer', 'Invoice Date', 'Product / Charge', 'Weight / Qty', 'Price (Rp)', 'Disc %', 'Disc Rp', 'Amount (Rp)'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->invoice?->invoice_number ?? '',
                                        $record->invoice?->customer?->name ?? '',
                                        $record->invoice?->invoice_date ?? '',
                                        $record->item_name ?? '',
                                        $record->weight ?? '',
                                        $record->price ?? '',
                                        $record->discount_percent ?? '',
                                        $record->discount_rp ?? '',
                                        $record->amount ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.invoice-details-pdf', [
                                'records' => $records,
                                'title' => __('Invoice Items Detail')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_invoice_items_detail.pdf');
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
