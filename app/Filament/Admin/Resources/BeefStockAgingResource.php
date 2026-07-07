<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BeefStockAgingResource\Pages;
use App\Models\BeefStock;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Support\Carbon;

class BeefStockAgingResource extends Resource
{
    protected static ?string $model = BeefStock::class;

    protected static ?string $cluster = \App\Filament\Clusters\BeefStocks::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function getNavigationLabel(): string
    {
        return __('Aging > 60 Days');
    }

    public static function getModelLabel(): string
    {
        return __('Aging Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Aging > 60 Days');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('status', 'IN_STOCK')
                    ->where('pack_date', '<=', Carbon::now()->subDays(60))
                    ->whereHas('product', function ($q) {
                        $q->where('name', 'like', '%CHILL%')
                          ->orWhereHas('category', function ($qc) {
                              $qc->where('name', 'like', '%CHILL%');
                          });
                    })
                    ->orderBy('pack_date', 'asc');
            })
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->alignCenter()
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('P.O.D'))
                    ->date('d-M-y')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('age')
                    ->label(__('Umur (Hari)'))
                    ->getStateUsing(fn (BeefStock $record) => $record->pack_date ? abs((int) now()->diffInDays($record->pack_date)) : '')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state >= 90 ? 'danger' : 'warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label(__('Warehouse'))
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeefStockAgings::route('/'),
        ];
    }
}
