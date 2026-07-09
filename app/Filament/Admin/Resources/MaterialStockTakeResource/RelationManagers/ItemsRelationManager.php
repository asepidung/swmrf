<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Material Counts';

    public function isReadOnly(): bool
    {
        return false; // Allow inline editing
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
        $opnameStatus = $this->getOwnerRecord()->status;
        $isReviewOrCompleted = in_array($opnameStatus, ['REVIEW', 'COMPLETED']);
        $isInProgress = in_array($opnameStatus, ['DRAFT', 'IN_PROGRESS']);

        return $table
            ->recordTitleAttribute('id')
            ->paginationPageOptions([50, 100, 200, 'all'])
            ->defaultPaginationPageOption(100)
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
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isReviewOrCompleted),
                
                // Only show text input if in progress
                Tables\Columns\TextInputColumn::make('physical_qty')
                    ->label(__('Physical Qty'))
                    ->type('number')
                    ->step('0.01')
                    ->visible($isInProgress)
                    ->updateStateUsing(function ($record, $state) {
                        $record->physical_qty = $state === '' ? null : (float) $state;
                        if ($record->physical_qty !== null) {
                            $record->difference_qty = $record->physical_qty - $record->system_qty;
                        } else {
                            $record->difference_qty = null;
                        }
                        $record->save();
                    }),
                
                // Show as text if review or completed
                Tables\Columns\TextColumn::make('physical_qty_text')
                    ->label(__('Physical Qty'))
                    ->getStateUsing(fn ($record) => $record->physical_qty)
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isReviewOrCompleted),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->visible($isReviewOrCompleted)
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
                    ->visible($opnameStatus === 'COMPLETED')
                    ->color(fn ($record) => $record->difference_qty == 0 ? 'success' : ($record->difference_qty > 0 ? 'info' : 'danger')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create action, items are auto-generated
            ])
            ->actions([
                // No inline actions
            ])
            ->bulkActions([
                // No bulk actions
            ]);
    }
}
