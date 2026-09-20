<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CostingResource\Pages;
use App\Filament\Admin\Resources\CostingResource\RelationManagers;
use App\Models\Costing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mesin HPP (issue #480) -- lihat `.agents/hpp.md` dan
 * `App\Services\CostingCalculator` untuk metodenya. Seluruh angka di sini
 * adalah SNAPSHOT (harga dikunci saat costing dibuat, `hpp.md` §6) --
 * tidak ada satu field pun yang diketik bebas kecuali `overhead_per_kg`,
 * dan hanya selagi `Draft`.
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
        return __('PRODUCTION');
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Costing'))
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
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->disabled(fn ($livewire): bool => static::isNotEditable($livewire)),
                    ])->columns(2),

                Forms\Components\Section::make(__('Summary'))
                    ->schema([
                        Forms\Components\Placeholder::make('purchase_cost')
                            ->label(__('Purchase Cost'))
                            ->content(fn (?Costing $record): string => 'Rp '.number_format((float) ($record?->purchase_cost ?? 0), 0, ',', '.')),

                        Forms\Components\Placeholder::make('total_sales_value')
                            ->label(__('Total Sales Value'))
                            ->content(fn (?Costing $record): string => 'Rp '.number_format((float) ($record?->total_sales_value ?? 0), 0, ',', '.')),

                        Forms\Components\Placeholder::make('ratio_k')
                            ->label(__('Ratio (k)'))
                            ->content(fn (?Costing $record): string => number_format((float) ($record?->ratio_k ?? 0), 6)),

                        Forms\Components\Placeholder::make('margin_percent')
                            ->label(__('Margin %'))
                            ->content(fn (?Costing $record): string => number_format($record?->marginPercent() ?? 0, 2).'%'),

                        Forms\Components\Placeholder::make('total_kg')
                            ->label(__('Total Kg'))
                            ->content(fn (?Costing $record): string => number_format((float) ($record?->total_kg ?? 0), 2).' Kg'),

                        Forms\Components\Placeholder::make('profit')
                            ->label(__('Profit'))
                            ->content(fn (?Costing $record): string => 'Rp '.number_format((float) ($record?->profit ?? 0), 0, ',', '.')),
                    ])
                    ->columns(3)
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
                    ->label(__('Ratio (k)'))
                    ->numeric(decimalPlaces: 6),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Costing::STATUS_DRAFT => 'warning',
                        Costing::STATUS_LOCKED => 'success',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        Costing::STATUS_DRAFT => Costing::STATUS_DRAFT,
                        Costing::STATUS_LOCKED => Costing::STATUS_LOCKED,
                    ]),

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
            ])
            ->actions([])
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
            'edit' => Pages\EditCosting::route('/{record}/edit'),
            'view' => Pages\ViewCosting::route('/{record}'),
        ];
    }
}
