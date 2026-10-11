<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BarcodeCostResource\Pages;
use App\Models\BarcodeCost;
use App\Support\BarcodeCosts;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Layar kecil Nilai Barcode (HPP tahap 2, issue #522): accounting mengisi
 * `cost_per_kg` barcode yang belum bernilai (mis. stok awal produk yang belum
 * pernah di-costing). Mengisi menimpa dan merambat ke turunan relabel; tercatat
 * di activity log `barcode_costs`.
 */
class BarcodeCostResource extends Resource
{
    protected static ?string $model = BarcodeCost::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationLabel(): string
    {
        return __('Barcode Costs');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('PRODUCTION');
    }

    public static function getModelLabel(): string
    {
        return __('Barcode Cost');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Barcode Costs');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->label(__('Barcode'))->searchable()->copyable(),
                Tables\Columns\TextColumn::make('product.name')->label(__('Product'))->searchable(),
                Tables\Columns\TextInputColumn::make('cost_per_kg')
                    ->label(__('Cost per kg'))
                    ->type('number')
                    ->step('0.01')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(fn (): bool => ! (auth()->user()?->can('update', new BarcodeCost()) ?? false))
                    ->updateStateUsing(function (BarcodeCost $record, $state): mixed {
                        $value = filled($state) ? (float) $state : null;

                        BarcodeCosts::set($record->barcode, $value, $record->source_type, $record->source_id, __('Entered manually'), $record->product_id);
                        BarcodeCosts::propagateFrom($record->barcode);

                        return $value;
                    }),
                Tables\Columns\TextColumn::make('source_type')->label(__('Source'))->badge(),
                Tables\Columns\TextColumn::make('valued_at')->label(__('Valued at'))->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('valued')
                    ->label(__('Valued'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Valued'))
                    ->falseLabel(__('Not valued yet'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('cost_per_kg'),
                        false: fn ($query) => $query->whereNull('cost_per_kg'),
                        blank: fn ($query) => $query,
                    )
                    ->default(false),
                Tables\Filters\SelectFilter::make('source_type')
                    ->label(__('Source'))
                    ->options(array_combine(BarcodeCost::SOURCES, BarcodeCost::SOURCES)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBarcodeCosts::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
