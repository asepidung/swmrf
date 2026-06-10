<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BeefStockResource\Pages;
use App\Filament\Admin\Resources\BeefStockResource\RelationManagers;
use App\Models\Product;
use App\Models\BeefStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Columns\Summarizers\Sum;

class BeefStockResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $cluster = \App\Filament\Clusters\BeefStocks::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationLabel(): string
    {
        return __('Beef Stock');
    }

    public static function getModelLabel(): string
    {
        return __('Beef Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Beef Stock');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_beef_stocks');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('products.*')
            ->addSelect([
                'chill_jonggol' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 1)
                    ->where('grade_id', 1)
                    ->where('status', 'IN_STOCK'),
                'frozen_jonggol' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 1)
                    ->where('grade_id', 2)
                    ->where('status', 'IN_STOCK'),
                'chill_perum' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 2)
                    ->where('grade_id', 1)
                    ->where('status', 'IN_STOCK'),
                'frozen_perum' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 2)
                    ->where('grade_id', 2)
                    ->where('status', 'IN_STOCK'),
                'total_qty' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'IN_STOCK'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Product Details'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('Code'))
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Name'))
                            ->disabled(),
                        Forms\Components\TextInput::make('category.name')
                            ->label(__('Category'))
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('category.name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Code'))
                    ->weight('bold')
                    ->alignCenter()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Product Name'))
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('chill_jonggol')
                    ->label(__('CHILL (J)'))
                    ->alignRight()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                    ->summarize(Sum::make()->label('')),

                Tables\Columns\TextColumn::make('frozen_jonggol')
                    ->label(__('FROZEN (J)'))
                    ->alignRight()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                    ->summarize(Sum::make()->label('')),

                Tables\Columns\TextColumn::make('chill_perum')
                    ->label(__('CHILL (P)'))
                    ->alignRight()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                    ->summarize(Sum::make()->label('')),

                Tables\Columns\TextColumn::make('frozen_perum')
                    ->label(__('FROZEN (P)'))
                    ->alignRight()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                    ->summarize(Sum::make()->label('')),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label(__('Total'))
                    ->alignRight()
                    ->weight('bold')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                    ->summarize(Sum::make()->label('')),
            ])
            ->filters([
                Tables\Filters\Filter::make('hide_empty')
                    ->label(__('Hide Empty Stock'))
                    ->default(true)
                    ->query(fn (Builder $query) => $query->whereHas('beefStocks', fn ($q) => $q->where('status', 'IN_STOCK'))),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->recordUrl(fn (Product $record): string => Pages\ViewBeefStock::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BeefStocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeefStocks::route('/'),
            'view' => Pages\ViewBeefStock::route('/{record}'),
        ];
    }
}
