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
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Material Counts Detail');
    }

    protected static string $relationship = 'items';


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
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('physical_qty')
                    ->label(__('Physical Qty'))
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.'),
                // Aturannya satu rumah di `MaterialStockTakeItem`.
                //
                // Berkas ini memakai kunci `__('Sesuai')` yang pada #285
                // sudah diganti di berkas sebelah DAN dihapus dari kedua
                // berkas bahasa -- sehingga kuncinya tidak punya terjemahan
                // sama sekali dan pengguna berbahasa Inggris membaca
                // "Sesuai". Perapian yang setengah jalan meninggalkan
                // kerusakan yang lebih senyap daripada sebelum dirapikan.
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->getStateUsing(fn (\App\Models\MaterialStockTakeItem $record): string => $record->varianceLabel())
                    ->badge()
                    ->color(fn ($state, \App\Models\MaterialStockTakeItem $record): string => $record->varianceColor()),
                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('Difference Qty'))
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.')
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
