<?php

namespace App\Filament\Admin\Resources\CostingResource\RelationManagers;

use App\Models\CostingItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Baris produk sebuah costing -- READ-ONLY. Seluruh angkanya snapshot
 * dari `CostingCalculator` saat dibuat/dihitung ulang; mengubahnya lewat
 * sini akan membuat tabel ini berbeda dari yang sungguhan dihitung.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product')),
                Tables\Columns\TextColumn::make('weight_kg')
                    ->label(__('Weight (Kg)'))
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('referenceGroup.name')
                    ->label(__('Reference Group'))
                    ->placeholder(__('General price')),
                Tables\Columns\TextColumn::make('gross_price')
                    ->label(__('Gross'))
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('trading_terms_percent')
                    ->label(__('Terms %'))
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('net_price')
                    ->label(__('Net'))
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('sales_value')
                    ->label(__('Sales Value'))
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('hpp_per_kg')
                    ->label(__('HPP / Kg'))
                    ->money('IDR', locale: 'id')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('flag')
                    ->label(__('Flag'))
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        CostingItem::FLAG_NO_REFERENCE => __('No reference group -- valued using general price'),
                        CostingItem::FLAG_NO_PRICE => __('No price found'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        CostingItem::FLAG_NO_REFERENCE => 'warning',
                        CostingItem::FLAG_NO_PRICE => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
