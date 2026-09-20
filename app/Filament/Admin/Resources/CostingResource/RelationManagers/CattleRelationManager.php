<?php

namespace App\Filament\Admin\Resources\CostingResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Biaya beli per kelas sapi -- READ-ONLY, snapshot dari
 * `CostingCalculator` (`hpp.md` §6: biaya beli TIDAK PERNAH satu harga
 * dikali berat total, selalu dijumlah per kelas).
 */
class CattleRelationManager extends RelationManager
{
    protected static string $relationship = 'cattle';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Cattle Purchase Cost');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('cattleClass.name')
                    ->label(__('Class')),
                Tables\Columns\TextColumn::make('head_count')
                    ->label(__('Head Count')),
                Tables\Columns\TextColumn::make('received_weight')
                    ->label(__('Received Weight (Kg)'))
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('price_per_kg')
                    ->label(__('Price / Kg'))
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money('IDR', locale: 'id')
                    ->weight('bold'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
