<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Material Counts Detail';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label(__('Item Code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Item Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('system_qty')
                    ->label(__('System Qty'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('physical_qty')
                    ->label(__('Physical Qty'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->getStateUsing(function ($record) {
                        if ($record->physical_qty === null) return '-';
                        if ($record->difference_qty > 0) return __('Lebih');
                        if ($record->difference_qty < 0) return __('Kurang');
                        return __('Sesuai');
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        __('Lebih') => 'info',
                        __('Kurang') => 'danger',
                        __('Sesuai') => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('Difference Qty'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn ($state) => $state > 0 ? 'info' : ($state < 0 ? 'danger' : 'success')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
