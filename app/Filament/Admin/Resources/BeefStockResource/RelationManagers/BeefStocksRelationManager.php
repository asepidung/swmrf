<?php

namespace App\Filament\Admin\Resources\BeefStockResource\RelationManagers;

use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BeefStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'beefStocks';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('barcode')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'IN_STOCK')->orderBy('pack_date', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->alignCenter()
                    ->searchable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Warehouse'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight (Kg)'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ',')),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 1) : '-'),

                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('P.O.D'))
                    ->date('d-M-y')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('age')
                    ->label(__('Umur'))
                    ->getStateUsing(fn (BeefStock $record) => $record->pack_date ? sprintf('%03d days', abs((int) now()->diffInDays($record->pack_date))) : '')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record || !$record->barcode) return $state;
                        $prefix = substr($record->barcode, 0, 1);
                        $map = [
                            '1' => 'BNG',
                            '2' => 'RSTK',
                            '3' => 'RIMP',
                            '4' => 'RRTN',
                            '5' => 'RTRD',
                            '6' => 'RLBT',
                            '7' => 'TRDL',
                            '8' => 'TRDI',
                        ];
                        return $map[$prefix] ?? $state;
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('delete')
                    ->label('')
                    ->tooltip(__('Hapus Stock'))
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->isProgrammer() || auth()->user()->hasPermission('delete_beef_stocks'))
                    ->action(function (BeefStock $record) {
                        BeefStockMovement::create([
                            'product_id' => $record->product_id,
                            'warehouse_id' => $record->warehouse_id,
                            'condition' => $record->grade_id,
                            'barcode' => $record->barcode,
                            'transaction_type' => 'VOID_STOCK',
                            'reference_document' => 'MANUAL_DELETE',
                            'weight_out' => $record->weight,
                            'pcs_out' => $record->qty_pcs,
                        ]);
                        $record->delete();
                    }),
            ])
            ->bulkActions([]);
    }
}
