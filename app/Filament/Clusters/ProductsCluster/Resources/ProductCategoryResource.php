<?php

namespace App\Filament\Clusters\ProductsCluster\Resources;

use App\Filament\Clusters\ProductsCluster;
use App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $cluster = ProductsCluster::class;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Beef Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Beef Categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(fn() => __('Category Name'))
                            ->required()
                            ->unique(ignorable: fn ($record) => $record)
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                        Forms\Components\TextInput::make('prefix')
                            ->label(fn() => __('Prefix (Kode)'))
                            ->required()
                            ->numeric()
                            ->unique(ignorable: fn ($record) => $record)
                            ->hint(__('Contoh: 1 untuk kategori A, 2 untuk kategori B')),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('Category Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('prefix')
                    ->label(fn() => __('Prefix'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(fn() => __('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Clickable rows handles edit redirection, actions left clean per project rules
            ])
            ->recordUrl(
                fn (ProductCategory $record): string => Pages\EditProductCategory::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
