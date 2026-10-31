<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CostingResource\Pages;
use App\Filament\Admin\Resources\CostingResource\RelationManagers;
use App\Models\Costing;
use App\Services\CostingPrices;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mesin HPP (issue #480) -- lihat `.agents/hpp.md` dan
 * `App\Services\CostingCalculator` untuk metodenya. Seluruh angka di sini
 * adalah SNAPSHOT (harga dikunci saat costing dibuat, `hpp.md` §6) --
 * tidak ada satu field pun yang diketik bebas kecuali `overhead_per_kg` dan
 * gross/net per produk (#541), dan hanya selagi `Draft`.
 */
class CostingResource extends Resource
{
    protected static ?string $model = Costing::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public static function getNavigationLabel(): string
    {
        return __('Costings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ACCOUNTING');
    }

    public static function getModelLabel(): string
    {
        return __('Costing');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Costings');
    }

    private static function isNotEditable($livewire): bool
    {
        return ! ($livewire instanceof CreateRecord) && $livewire->getRecord()->status !== Costing::STATUS_DRAFT;
    }

    private static function rp(mixed $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    /** Satu angka ringkasan; bila ada $tooltip, ikon tanda tanya menjelaskan perhitungannya dengan angka costing itu. */
    private static function figure(string $name, string $label, \Closure $content, ?\Closure $tooltip = null): Forms\Components\Placeholder
    {
        $field = Forms\Components\Placeholder::make($name)
            ->label($label)
            ->content(fn (?Costing $record): string => $record ? $content($record) : '-');

        if ($tooltip) {
            $field->hintIcon('heroicon-m-question-mark-circle', fn (?Costing $record): ?string => $record ? $tooltip($record) : null);
        }

        return $field;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Costing'))
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('costing_number')
                            ->label(__('Costing Number'))
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('boning_id')
                            ->label(__('Boning'))
                            ->relationship('boning', 'doc_no')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('costing_date')
                            ->label(__('Costing Date'))
                            ->default(now())
                            ->disabled(fn ($livewire): bool => static::isNotEditable($livewire))
                            ->required(),

                        Forms\Components\TextInput::make('overhead_per_kg')
                            ->label(__('Overhead per Kg'))
                            ->prefix('Rp')
                            ->formatStateUsing(fn ($state): string => CostingPrices::formatMoney($state ?? 0))
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                            ->dehydrateStateUsing(fn ($state): float => CostingPrices::parseMoney($state))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($livewire, $state): void {
                                if ($livewire instanceof Pages\EditCosting) {
                                    $livewire->applyOverhead(CostingPrices::parseMoney($state));
                                }
                            })
                            ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'decimal', 'x-on:focus' => 'if ($el.value === "0") $el.select()'])
                            ->required()
                            ->disabled(fn ($livewire): bool => static::isNotEditable($livewire)),
                    ])->columns(['default' => 2, 'lg' => 4]),

                Forms\Components\Section::make(__('Summary'))
                    ->compact()
                    ->schema([
                        static::figure('purchase_cost', __('Purchase Cost'), fn (Costing $c): string => static::rp($c->purchase_cost)),

                        static::figure('total_sales_value', __('Total Sales Value'), fn (Costing $c): string => static::rp($c->total_sales_value)),

                        static::figure('ratio_k', __('Purchase cost factor (k)'), fn (Costing $c): string => number_format((float) $c->ratio_k, 6),
                            fn (Costing $c): string => __('Purchase cost :cost / net sales value :sales = :k. Buying price per kg = net price x k.', [
                                'cost' => static::rp($c->purchase_cost), 'sales' => static::rp($c->total_sales_value), 'k' => number_format((float) $c->ratio_k, 6),
                            ])),

                        static::figure('total_kg', __('Total Kg'), fn (Costing $c): string => number_format((float) $c->total_kg, 2).' Kg'),

                        static::figure('overhead_total', __('Total Overhead'), fn (Costing $c): string => static::rp($c->overhead_total),
                            fn (Costing $c): string => __('Overhead :oh per kg x meat weight (without offal and hides) = :total.', [
                                'oh' => static::rp($c->overhead_per_kg), 'total' => static::rp($c->overhead_total),
                            ])),

                        static::figure('profit_before_overhead', __('Profit before overhead'), fn (Costing $c): string => static::rp($c->profitBeforeOverhead()),
                            fn (Costing $c): string => __('Net sales value :sales - purchase cost :cost = :value.', [
                                'sales' => static::rp($c->total_sales_value), 'cost' => static::rp($c->purchase_cost), 'value' => static::rp($c->profitBeforeOverhead()),
                            ])),

                        static::figure('profit', __('Profit'), fn (Costing $c): string => static::rp($c->profit),
                            fn (Costing $c): string => __('Profit before overhead :before - total overhead :oh = :profit.', [
                                'before' => static::rp($c->profitBeforeOverhead()), 'oh' => static::rp($c->overhead_total), 'profit' => static::rp($c->profit),
                            ])),

                        static::figure('margin_percent', __('Margin % (Net)'), fn (Costing $c): string => number_format($c->marginPercent(), 2).'%',
                            fn (Costing $c): string => __('Profit :profit / net sales value :sales = :margin.', [
                                'profit' => static::rp($c->profit), 'sales' => static::rp($c->total_sales_value), 'margin' => number_format($c->marginPercent(), 2).'%',
                            ])),

                        static::figure('margin_gross_percent', __('Margin % (Gross)'), fn (Costing $c): string => number_format($c->marginGrossPercent(), 2).'%',
                            fn (Costing $c): string => __('1 - (purchase cost + overhead) / sales value at gross prices :gross = :margin.', [
                                'gross' => static::rp($c->grossSalesValue()), 'margin' => number_format($c->marginGrossPercent(), 2).'%',
                            ])),
                    ])
                    ->columns(['default' => 2, 'sm' => 3, 'xl' => 5])
                    ->visible(fn (?Costing $record): bool => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('costing_number')
                    ->label(__('Costing Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('costing_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('boning.doc_no')
                    ->label(__('Boning'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchase_cost')
                    ->label(__('Purchase Cost'))
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_sales_value')
                    ->label(__('Total Sales Value'))
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ratio_k')
                    ->label(__('Purchase cost factor (k)'))
                    ->numeric(decimalPlaces: 6),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Costing::STATUS_DRAFT ? __('Saved') : $state)
                    ->color(fn (string $state): string => match ($state) {
                        Costing::STATUS_DRAFT => 'warning',
                        Costing::STATUS_LOCKED => 'success',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('boning')->get();

                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Number', 'Date', 'Boning', 'Purchase Cost', 'Total Sales Value', 'Ratio (k)', 'Profit', 'Status',
                                ]));

                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->costing_number,
                                        optional($record->costing_date)->format('Y-m-d') ?? '',
                                        $record->boning?->doc_no ?? '',
                                        (float) $record->purchase_cost,
                                        (float) $record->total_sales_value,
                                        (float) $record->ratio_k,
                                        (float) $record->profit,
                                        $record->status,
                                    ]));
                                }

                                $writer->close();
                            }, 'costings.xlsx');
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('boning')->get();

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.costings-pdf', [
                                'records' => $records,
                                'title' => __('Costings'),
                            ]);

                            return response()->streamDownload(fn () => print($pdf->output()), 'costings.pdf');
                        }),
                ])
                    ->label(__('Export Data'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->button()
                    ->color('success'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('saved_at'))
            ->defaultSort('created_at', 'desc')
            ->filters([

                // Silent date filter, standar modul transaksional (rujukan CashBookResource).
                Tables\Filters\Filter::make('costing_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
                        $until = $data['until'] ?? now()->format('Y-m-d');

                        return $query
                            ->whereDate('costing_date', '>=', $from)
                            ->whereDate('costing_date', '<=', $until);
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $defaultFrom = now()->startOfMonth()->format('Y-m-d');
                        $defaultUntil = now()->format('Y-m-d');

                        if (($data['from'] ?? null) && $data['from'] !== $defaultFrom) {
                            $indicators[] = Tables\Filters\Indicator::make(__('From').': '.\Carbon\Carbon::parse($data['from'])->format('d M Y'))
                                ->removeField('from');
                        }

                        if (($data['until'] ?? null) && $data['until'] !== $defaultUntil) {
                            $indicators[] = Tables\Filters\Indicator::make(__('Until').': '.\Carbon\Carbon::parse($data['until'])->format('d M Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        Costing::STATUS_DRAFT => __('Saved'),
                        Costing::STATUS_LOCKED => Costing::STATUS_LOCKED,
                    ]),
            ])
            ->actions([
                // Costing yang belum dikunci boleh dihapus; yang terkunci tidak (hapus ditolak model).
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn (Costing $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->visible(fn (Costing $record): bool => $record->status === Costing::STATUS_DRAFT),
            ])
            ->bulkActions([])
            ->recordUrl(fn (Costing $record): string => $record->status === Costing::STATUS_DRAFT
                ? static::getUrl('edit', ['record' => $record])
                : static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\CattleRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostings::route('/'),
            'draft' => Pages\DraftCosting::route('/draft'),
            'edit' => Pages\EditCosting::route('/{record}/edit'),
            'view' => Pages\ViewCosting::route('/{record}'),
        ];
    }
}
