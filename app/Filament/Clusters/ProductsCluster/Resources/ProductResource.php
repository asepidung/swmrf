<?php

namespace App\Filament\Clusters\ProductsCluster\Resources;

use App\Filament\Clusters\ProductsCluster;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $cluster = ProductsCluster::class;

    public static function getModelLabel(): string
    {
        return __('Product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Products');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Basic Information'))
                    ->schema([
                        Forms\Components\Radio::make('structure_type')
                            ->label(__('Product Structure'))
                            ->options([
                                'main' => __('Main Product'),
                                'sub' => __('Sub Product / Variant'),
                            ])
                            ->default('main')
                            ->live()
                            ->disabled(fn ($context) => $context === 'edit')
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                if ($get('structure_type') === 'main') {
                                    $set('parent_id', null);
                                }
                                static::updateCode($set, $get);
                            }),

                        Forms\Components\Select::make('parent_id')
                            ->label(__('Parent Product'))
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereNull('parent_id')->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn ($context) => $context === 'edit')
                            ->visible(fn (Forms\Get $get) => $get('structure_type') === 'sub')
                            ->required(fn (Forms\Get $get) => $get('structure_type') === 'sub')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state) {
                                    $parent = Product::find($state);
                                    if ($parent) {
                                        $set('category_id', $parent->category_id);
                                    }
                                }
                                static::updateCode($set, $get);
                            }),

                        Forms\Components\Select::make('category_id')
                            ->label(__('Category'))
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Forms\Get $get, $context) => $context === 'edit' || $get('structure_type') === 'sub')
                            ->dehydrated()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                static::updateCode($set, $get);
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Category Name'))
                                    ->required()
                                    ->unique('product_categories', 'name')
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                            ])
                            ->createOptionAction(
                                fn (Forms\Components\Actions\Action $action) => $action->modalWidth('md')->color('warning')
                            ),

                        Forms\Components\TextInput::make('code')
                            ->label(__('Product Code'))
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->unique(ignorable: fn ($record) => $record),

                        Forms\Components\TextInput::make('name')
                            ->label(__('Product Name'))
                            ->required()
                            ->unique(ignorable: fn ($record) => $record)
                            ->validationMessages([
                                'unique' => 'Nama barang ini sudah terdaftar di sistem, pakai nama lain!',
                            ])
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Status'))
                            ->default(true),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Product Code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Product Name'))
                    ->searchable()
                    ->sortable()
                    ->description(function (Product $record) {
                        if ($record->structure_type === 'main') {
                            return __('Main Product');
                        }
                        if ($record->structure_type === 'sub' && $record->parent) {
                            return __('Variant of') . ': ' . $record->parent->name;
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('Status')),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Clickable rows handles edit redirection, actions left clean per project rules
            ])
            ->recordUrl(
                fn (Product $record): string => Pages\EditProduct::getUrl([$record->id])
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function updateCode(callable $set, callable $get): void
    {
        $structureType = $get('structure_type');

        if ($structureType === 'main') {
            $categoryId = $get('category_id');
            if (!$categoryId) {
                $set('code', null);
                return;
            }

            $categoryId = (int) $categoryId;
            $maxUrut = Product::where('category_id', $categoryId)
                ->whereNull('parent_id')
                ->get()
                ->map(function ($product) use ($categoryId) {
                    $code = (int) $product->code;
                    return (int) (($code - ($categoryId * 100000)) / 100);
                })
                ->max() ?? 0;

            $nomorUrut = $maxUrut + 1;
            $code = ($categoryId * 100000) + ($nomorUrut * 100);
            $set('code', (string) $code);
        } elseif ($structureType === 'sub') {
            $parentId = $get('parent_id');
            if (!$parentId) {
                $set('code', null);
                return;
            }

            $parent = Product::find($parentId);
            if (!$parent) {
                $set('code', null);
                return;
            }

            $parentCode = (int) $parent->code;
            $count = Product::where('parent_id', $parentId)->count();
            $nextCode = $parentCode + $count + 1;

            // Collision check
            while (Product::where('code', $nextCode)->exists()) {
                $count++;
                $nextCode = $parentCode + $count + 1;
            }

            $set('code', (string) $nextCode);
        } else {
            $set('code', null);
        }
    }
}
