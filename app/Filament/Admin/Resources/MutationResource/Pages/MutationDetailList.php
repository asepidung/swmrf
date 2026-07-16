<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use App\Models\MutationItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class MutationDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MutationResource::class;

    protected static string $view = 'filament.admin.resources.mutation-resource.pages.detail-list';

    public function getTitle(): string { return __('Mutation Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(MutationItem::query()->with(['mutation.fromWarehouse', 'mutation.toWarehouse', 'product', 'grade']))
            ->columns([
                Tables\Columns\TextColumn::make('mutation.doc_no')
                    ->label(__('Mutation No'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mutation.mutation_date')
                    ->label(__('Mutation Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mutation.fromWarehouse.name')
                    ->label(__('From Warehouse'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mutation.toWarehouse.name')
                    ->label(__('To Warehouse'))
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
                Tables\Columns\IconColumn::make('is_received')
                    ->label(__('Received'))
                    ->boolean()
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.mutation-details-pdf', ['records' => $records])->setPaper('a4', 'landscape');
                            return response()->streamDownload(fn () => print($pdf->output()), 'Detail_Mutation_' . now()->format('Y-m-d') . '.pdf');
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
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Mutation No', 'Mutation Date', 'From Warehouse', 'To Warehouse', 'Barcode', 'Product', 'Weight', 'Grade', 'Received']));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->mutation->doc_no ?? '',
                                        $record->mutation->mutation_date ? \Carbon\Carbon::parse($record->mutation->mutation_date)->format('Y-m-d') : '',
                                        $record->mutation->fromWarehouse->name ?? '',
                                        $record->mutation->toWarehouse->name ?? '',
                                        $record->barcode ?? '',
                                        $record->product->name ?? '',
                                        (string) $record->weight,
                                        $record->grade->name ?? '',
                                        $record->is_received ? 'Yes' : 'No',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Mutation_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('mutation_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('From Date')),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['until'] ?? now()->toDateString();

                        return $query->whereHas('mutation', function ($q) use ($from, $until) {
                            $q->whereDate('mutation_date', '>=', $from)
                              ->whereDate('mutation_date', '<=', $until);
                        });
                    }),
            ]);
    }
}
